<?php

namespace Tests\Feature\Stay;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Immo\Models\Property;
use App\Modules\Stay\Models\Stay;
use Database\Seeders\CommunesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SenegalGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Gestion de la config « nuitées » d'un bien par son propriétaire (F4.3).
 *
 * Couvre l'upsert (création puis mise à jour de la config), le retrait/la
 * désactivation, l'isolation entre propriétaires et l'exposition de la config
 * sur la fiche privée du bien.
 */
class PropertyStayManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, SenegalGeographySeeder::class, CommunesSeeder::class]);
    }

    private function proprietaire(): User
    {
        $user = User::factory()->create(); // vérifié par défaut (email_verified_at)
        $user->assignRole(UserRole::PROPRIETAIRE->value);

        return $user;
    }

    /** Données de config nuitées valides. */
    private function config(array $surcharge = []): array
    {
        return array_merge([
            'price_per_night_xof' => 45_000,
            'caution_xof' => 100_000,
            'capacity' => 4,
            'min_nights' => 2,
            'max_nights' => 15,
            'check_in_time' => '15:00',
            'check_out_time' => '11:00',
        ], $surcharge);
    }

    public function test_un_proprietaire_active_le_mode_nuitees_de_son_bien(): void
    {
        $owner = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/properties/{$property->id}/stay", $this->config())
            ->assertCreated()
            ->assertJsonPath('data.stay.price_per_night_xof', 45_000)
            ->assertJsonPath('data.stay.capacity', 4);

        $this->assertDatabaseHas('stays', [
            'property_id' => $property->id,
            'price_per_night_xof' => 45_000,
            'is_active' => true,
        ]);
    }

    public function test_reenregistrer_met_a_jour_la_config_existante(): void
    {
        $owner = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        Stay::factory()->create(['property_id' => $property->id, 'price_per_night_xof' => 30_000]);

        Sanctum::actingAs($owner);

        // 200 (et non 201) car la config existe déjà ; un seul enregistrement.
        $this->putJson("/api/v1/properties/{$property->id}/stay", $this->config(['price_per_night_xof' => 60_000]))
            ->assertOk()
            ->assertJsonPath('data.stay.price_per_night_xof', 60_000);

        $this->assertDatabaseCount('stays', 1);
    }

    public function test_reenregistrer_reactive_une_config_desactivee(): void
    {
        $owner = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        Stay::factory()->inactive()->create(['property_id' => $property->id]);

        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/properties/{$property->id}/stay", $this->config())->assertOk();

        $this->assertDatabaseHas('stays', ['property_id' => $property->id, 'is_active' => true]);
    }

    public function test_prix_par_nuit_obligatoire(): void
    {
        $owner = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/properties/{$property->id}/stay", $this->config(['price_per_night_xof' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price_per_night_xof']);
    }

    public function test_max_nuits_inferieur_au_min_est_refuse(): void
    {
        $owner = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/properties/{$property->id}/stay", $this->config(['min_nights' => 10, 'max_nights' => 3]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_nights']);
    }

    public function test_un_proprietaire_ne_peut_pas_configurer_le_bien_d_un_autre(): void
    {
        $owner = $this->proprietaire();
        $autre = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $autre->id]);

        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/properties/{$property->id}/stay", $this->config())->assertStatus(403);
    }

    public function test_retirer_le_mode_nuitees_supprime_la_config_sans_reservation(): void
    {
        $owner = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        Stay::factory()->create(['property_id' => $property->id]);

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/properties/{$property->id}/stay")
            ->assertOk()
            ->assertJsonPath('data.message', 'Mode nuitées retiré.');

        // ⚠️ Depuis la corbeille (F11.4), retirer le mode nuitées n'efface plus
        // la ligne : la configuration part à la corbeille du propriétaire et
        // reste récupérable 30 jours. Le bien, lui, cesse bien d'être réservable
        // à la nuit — c'est ce que vérifient les tests de catalogue.
        $this->assertSoftDeleted('stays', ['property_id' => $property->id]);
    }

    public function test_retirer_le_mode_nuitees_desactive_si_reservations(): void
    {
        $owner = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $stay = Stay::factory()->create(['property_id' => $property->id]);

        // Une réservation existante empêche la suppression (intégrité historique).
        $stay->bookings()->create([
            'reference' => 'BK-TEST-1',
            'user_id' => $this->proprietaire()->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'guests' => 2,
            'amount_xof' => 90_000,
            'status' => 'confirmee',
        ]);

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/properties/{$property->id}/stay")->assertOk();

        // La config est conservée mais désactivée (retirée du catalogue public).
        $this->assertDatabaseHas('stays', ['id' => $stay->id, 'is_active' => false]);
    }

    public function test_retirer_sans_config_renvoie_404(): void
    {
        $owner = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/properties/{$property->id}/stay")->assertStatus(404);
    }

    public function test_la_config_exige_authentification(): void
    {
        $property = Property::factory()->create();

        $this->putJson("/api/v1/properties/{$property->id}/stay", $this->config())->assertStatus(401);
    }

    public function test_la_fiche_privee_du_bien_expose_sa_config_nuitees(): void
    {
        $owner = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        Stay::factory()->create(['property_id' => $property->id, 'price_per_night_xof' => 55_000]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/properties/mine/{$property->id}")
            ->assertOk()
            ->assertJsonPath('data.stay.price_per_night_xof', 55_000);
    }

    public function test_la_fiche_privee_d_un_bien_sans_nuitees_a_stay_null(): void
    {
        $owner = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/properties/mine/{$property->id}")
            ->assertOk()
            ->assertJsonPath('data.stay', null);
    }
}
