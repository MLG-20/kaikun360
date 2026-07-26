<?php

namespace Tests\Feature\Mobility;

use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Models\Profile;
use App\Modules\Mobility\Enums\VehicleType;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Mobility\Notifications\NewVehicleToValidateNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests catalogue + publication + validation des véhicules (phase B7.3),
 * y compris le blocage de validation pour conformité incomplète.
 */
class VehicleCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function verifiedProvider(): User
    {
        $provider = User::factory()->create();
        $provider->assignRole(UserRole::PRESTATAIRE->value);
        Profile::factory()->prestataire()->verifie()->create(['user_id' => $provider->id]);

        return $provider;
    }

    private function agent(): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);
        $agent->givePermissionTo(AdminPermission::operational()); // F7.1.b : agent pleinement outillé (droits désormais délégués, plus portés par le rôle)

        return $agent;
    }

    public function test_le_catalogue_ne_montre_que_les_vehicules_publies(): void
    {
        Vehicle::factory()->published()->count(2)->create();
        Vehicle::factory()->create(); // en attente

        $this->getJson('/api/v1/vehicles')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_le_detail_d_un_vehicule_non_publie_renvoie_404(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->getJson("/api/v1/vehicles/{$vehicle->id}")->assertStatus(404);
    }

    public function test_un_prestataire_verifie_publie_un_vehicule_et_notifie_les_agents(): void
    {
        Notification::fake();
        $agent = $this->agent();

        Sanctum::actingAs($this->verifiedProvider());

        $this->postJson('/api/v1/vehicles', [
            'type' => VehicleType::MINIBUS->value,
            'brand' => 'Toyota',
            'capacity' => 15,
            'price_per_day_xof' => 120_000,
            'has_driver' => true,
            'insurance_ref' => 'ASS-123',
            'driver_identity' => 'Modou Fall',
        ])
            ->assertCreated()
            ->assertJsonPath('data.vehicle.status', 'en_attente_validation');

        Notification::assertSentTo($agent, NewVehicleToValidateNotification::class);
    }

    public function test_un_non_prestataire_verifie_ne_peut_pas_publier(): void
    {
        Sanctum::actingAs(User::factory()->create()); // simple client

        $this->postJson('/api/v1/vehicles', [
            'type' => VehicleType::BUS->value,
            'capacity' => 30,
            'price_per_day_xof' => 200_000,
        ])->assertStatus(403);
    }

    public function test_un_prestataire_modifie_son_vehicule_mais_pas_celui_d_un_autre(): void
    {
        $provider = $this->verifiedProvider();
        $vehicle = Vehicle::factory()->create(['provider_id' => $provider->id]);

        Sanctum::actingAs($provider);
        $this->patchJson("/api/v1/vehicles/{$vehicle->id}", ['price_per_day_xof' => 90_000])
            ->assertOk()
            ->assertJsonPath('data.vehicle.price_per_day_xof', 90_000);

        $autre = Vehicle::factory()->create(); // autre prestataire
        $this->patchJson("/api/v1/vehicles/{$autre->id}", ['price_per_day_xof' => 1])
            ->assertStatus(403);
    }

    public function test_un_agent_valide_un_vehicule_motorise_conforme(): void
    {
        $vehicle = Vehicle::factory()->create(); // motorisé conforme par défaut
        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/vehicles/{$vehicle->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.vehicle.status', 'publie');
    }

    public function test_la_validation_echoue_si_conformite_motorisee_incomplete(): void
    {
        $vehicle = Vehicle::factory()->create(['insurance_ref' => null]); // assurance manquante
        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/vehicles/{$vehicle->id}/approve")
            ->assertStatus(422)
            ->assertJsonValidationErrors('compliance');
    }

    public function test_la_validation_d_une_pirogue_exige_les_champs_de_securite(): void
    {
        $agent = $this->agent();

        // Pirogue sans gilets/météo/conformité → refus.
        $pirogue = Vehicle::factory()->pirogue()->create();
        Sanctum::actingAs($agent);
        $this->patchJson("/api/v1/vehicles/{$pirogue->id}/approve")
            ->assertStatus(422)
            ->assertJsonValidationErrors('compliance');

        // Pirogue conforme → validée.
        $conforme = Vehicle::factory()->pirogueConforme()->create();
        $this->patchJson("/api/v1/vehicles/{$conforme->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.vehicle.status', 'publie');
    }

    public function test_un_non_agent_ne_peut_pas_valider(): void
    {
        $vehicle = Vehicle::factory()->create();
        Sanctum::actingAs($this->verifiedProvider());

        $this->patchJson("/api/v1/vehicles/{$vehicle->id}/approve")->assertStatus(403);
    }
}
