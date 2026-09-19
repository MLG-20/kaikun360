<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Models\Profile;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Immo\Models\Property;
use App\Modules\Mobility\Enums\VehicleType;
use App\Modules\Mobility\Models\Vehicle;
use Database\Seeders\CommunesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SenegalGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Attaques simulées contre le dépôt et la gestion des offres (F21.1).
 *
 * Chaque test joue le rôle de l'attaquant et vérifie que la plateforme REFUSE —
 * ou, quand elle accepte la requête, qu'elle ignore la partie piégée. Ce qui est
 * protégé, dans l'ordre :
 *   1. un déposant ordinaire ne se publie jamais lui-même ni ne se fait passer
 *      pour l'équipe Kaikun 360 ;
 *   2. l'offre, la corbeille et le mandat d'autrui restent hors d'atteinte ;
 *   3. les filtres du catalogue et les fichiers déposés n'ouvrent aucune brèche.
 */
class OfferAttacksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, SenegalGeographySeeder::class, CommunesSeeder::class]);
    }

    private function prestataire(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::PRESTATAIRE->value);
        Profile::factory()->prestataire()->verifie()->create(['user_id' => $user->id]);

        return $user;
    }

    private function proprietaire(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::PROPRIETAIRE->value);

        return $user;
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::SUPER_ADMIN->value);

        return $user;
    }

    // =========================================================================
    // 1. Élévation de droits : se publier soi-même, usurper l'équipe
    // =========================================================================

    public function test_un_prestataire_ne_peut_pas_publier_directement_un_vehicule_par_injection_de_champs(): void
    {
        $pirate = $this->prestataire();
        $victime = $this->superAdmin();
        Sanctum::actingAs($pirate);

        $reponse = $this->postJson('/api/v1/vehicles', [
            'type' => VehicleType::MINIBUS->value,
            'capacity' => 15,
            'price_per_day_xof' => 100_000,
            // Champs qu'un attaquant tente de forcer.
            'status' => 'publie',
            'approved_by' => $victime->id,
            'published_at' => now()->toDateTimeString(),
            'provider_id' => $victime->id,
        ])->assertCreated();

        $reponse->assertJsonPath('data.vehicle.status', 'en_attente_validation')
            ->assertJsonPath('data.vehicle.published_by_kaikun', false);

        $this->assertDatabaseHas('vehicles', [
            'id' => $reponse->json('data.vehicle.id'),
            'provider_id' => $pirate->id,
            'approved_by' => null,
        ]);
    }

    public function test_un_prestataire_ne_peut_pas_publier_directement_un_circuit_par_injection_de_champs(): void
    {
        $pirate = $this->prestataire();
        $victime = $this->superAdmin();
        Sanctum::actingAs($pirate);

        $reponse = $this->postJson('/api/v1/experiences', [
            'title' => 'Circuit piégé',
            'destination' => 'Saly',
            'duration_days' => 2,
            'price_xof' => 50_000,
            'departures' => [['start_date' => now()->addWeek()->toDateString(), 'seats_total' => 5]],
            'status' => 'publie',
            'approved_by' => $victime->id,
            'provider_id' => $victime->id,
        ])->assertCreated();

        $reponse->assertJsonPath('data.experience.status', 'en_attente_validation')
            ->assertJsonPath('data.experience.published_by_kaikun', false);

        $this->assertDatabaseHas('tourism_experiences', [
            'id' => $reponse->json('data.experience.id'),
            'provider_id' => $pirate->id,
        ]);
    }

    public function test_un_proprietaire_ne_peut_pas_publier_directement_un_bien_par_injection_de_champs(): void
    {
        $pirate = $this->proprietaire();
        $victime = $this->superAdmin();
        Sanctum::actingAs($pirate);

        $region = \App\Models\Region::where('name', 'Dakar')->first();

        $reponse = $this->postJson('/api/v1/properties', [
            'type' => 'villa',
            'title' => 'Villa piégée',
            'region_id' => $region->id,
            'department_id' => $region->departments()->first()->id,
            'status' => 'publie',
            'approved_by' => $victime->id,
            'owner_id' => $victime->id,
        ])->assertCreated();

        $reponse->assertJsonPath('data.property.status', 'en_attente_validation')
            ->assertJsonPath('data.property.published_by_kaikun', false);

        $this->assertDatabaseHas('properties', [
            'id' => $reponse->json('data.property.id'),
            'owner_id' => $pirate->id,
        ]);
    }

    public function test_un_client_ne_peut_pas_deposer_d_offre_du_tout(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/vehicles', ['type' => 'minibus', 'capacity' => 10, 'price_per_day_xof' => 1])->assertForbidden();
        $this->postJson('/api/v1/experiences', ['title' => 'x', 'destination' => 'y', 'duration_days' => 1, 'price_xof' => 1])->assertForbidden();
    }

    public function test_un_pretendu_role_dans_le_corps_de_la_requete_ne_donne_aucun_droit(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/vehicles', [
            'type' => 'minibus', 'capacity' => 10, 'price_per_day_xof' => 1,
            'role' => 'super_admin', 'roles' => ['super_admin'], 'is_admin' => true,
        ])->assertForbidden();
    }

    // =========================================================================
    // 2. L'offre, la corbeille et le mandat d'autrui
    // =========================================================================

    public function test_un_prestataire_ne_modifie_ni_ne_supprime_le_vehicule_d_un_autre(): void
    {
        $victime = Vehicle::factory()->published()->create(['provider_id' => $this->prestataire()->id]);
        Sanctum::actingAs($this->prestataire());

        $this->patchJson("/api/v1/vehicles/{$victime->id}", ['capacity' => 1])->assertForbidden();
        $this->deleteJson("/api/v1/vehicles/{$victime->id}")->assertForbidden();
        $this->assertNull($victime->fresh()->deleted_at);
    }

    public function test_un_prestataire_ne_modifie_ni_ne_supprime_le_circuit_d_un_autre(): void
    {
        $victime = TourismExperience::factory()->published()->create(['provider_id' => $this->prestataire()->id]);
        Sanctum::actingAs($this->prestataire());

        $this->patchJson("/api/v1/experiences/{$victime->id}", ['title' => 'Piraté'])->assertForbidden();
        $this->deleteJson("/api/v1/experiences/{$victime->id}")->assertForbidden();
    }

    public function test_un_proprietaire_ne_supprime_pas_le_bien_d_un_autre_ni_celui_de_l_equipe(): void
    {
        $equipe = Property::factory()->published()->create(['owner_id' => $this->superAdmin()->id]);
        $autre = Property::factory()->published()->create(['owner_id' => $this->proprietaire()->id]);
        Sanctum::actingAs($this->proprietaire());

        $this->deleteJson("/api/v1/properties/{$equipe->id}")->assertForbidden();
        $this->deleteJson("/api/v1/properties/{$autre->id}")->assertForbidden();
        $this->assertNull($equipe->fresh()->deleted_at);
    }

    public function test_le_super_admin_ne_touche_pas_a_l_offre_d_un_prestataire_externe(): void
    {
        $externe = Vehicle::factory()->published()->create(['provider_id' => $this->prestataire()->id]);
        Sanctum::actingAs($this->superAdmin());

        $this->patchJson("/api/v1/vehicles/{$externe->id}", ['capacity' => 1])->assertForbidden();
        $this->deleteJson("/api/v1/vehicles/{$externe->id}")->assertForbidden();
    }

    public function test_on_ne_purge_ni_ne_restaure_la_corbeille_d_un_autre_par_devinette_d_identifiant(): void
    {
        $victime = $this->superAdmin();
        $bien = Property::factory()->create(['owner_id' => $victime->id]);
        $bien->delete();

        Sanctum::actingAs($this->proprietaire());

        $this->deleteJson("/api/v1/me/trash/property/{$bien->id}")->assertNotFound();
        $this->postJson("/api/v1/me/trash/property/{$bien->id}/restore")->assertNotFound();
        $this->deleteJson('/api/v1/me/trash')->assertOk()->assertJsonPath('data.deleted', 0);

        $this->assertSoftDeleted('properties', ['id' => $bien->id]);
    }

    public function test_la_corbeille_refuse_les_identifiants_piegés_et_les_types_inconnus(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $this->deleteJson('/api/v1/me/trash/property/1%20OR%201=1')->assertStatus(404);
        $this->deleteJson('/api/v1/me/trash/users/1')->assertNotFound();
        $this->deleteJson('/api/v1/me/trash/notification/'.\Illuminate\Support\Str::uuid())->assertNotFound();
    }

    public function test_les_mandats_d_un_proprietaire_externe_sont_intouchables_meme_par_le_super_admin(): void
    {
        $mandat = \App\Modules\Manage\Models\ManagementMandate::factory()->create();
        Sanctum::actingAs($this->superAdmin());

        $this->patchJson("/api/v1/manage/mandates/{$mandat->id}", ['commission_rate' => 0])->assertForbidden();
        $this->deleteJson("/api/v1/manage/mandates/{$mandat->id}")->assertForbidden();
    }

    public function test_un_anonyme_n_atteint_aucune_route_de_gestion(): void
    {
        $this->postJson('/api/v1/vehicles', [])->assertUnauthorized();
        $this->deleteJson('/api/v1/me/trash')->assertUnauthorized();
        $this->deleteJson('/api/v1/vehicles/1')->assertUnauthorized();
        $this->patchJson('/api/v1/manage/mandates/1', [])->assertUnauthorized();
    }

    // =========================================================================
    // 3. Filtres du catalogue, fichiers déposés, offres non publiées
    // =========================================================================

    public function test_une_injection_sql_dans_les_filtres_du_catalogue_ne_renvoie_rien_de_plus(): void
    {
        Vehicle::factory()->published()->count(2)->create();
        Vehicle::factory()->create(); // en attente : ne doit JAMAIS sortir

        foreach (["' OR '1'='1", "1; DROP TABLE vehicles;--", "' UNION SELECT * FROM users--"] as $piege) {
            $reponse = $this->getJson('/api/v1/vehicles?'.http_build_query(['q' => $piege, 'type' => $piege]));

            $this->assertContains($reponse->status(), [200, 422], "Réponse inattendue pour {$piege}");
            if ($reponse->status() === 200) {
                $this->assertLessThanOrEqual(2, count($reponse->json('data')));
            }
        }

        $this->assertDatabaseCount('vehicles', 3); // la table est toujours là
    }

    public function test_un_faux_fichier_image_est_refuse_au_depot_de_photo(): void
    {
        Storage::fake('public');
        $proprietaire = $this->prestataire();
        $vehicule = Vehicle::factory()->create(['provider_id' => $proprietaire->id]);
        Sanctum::actingAs($proprietaire);

        $script = UploadedFile::fake()->createWithContent('photo.jpg', '<?php system($_GET["c"]); ?>');

        $this->postJson('/api/v1/media/upload', [
            'mediable_type' => 'vehicle',
            'mediable_id' => $vehicule->id,
            'file' => $script,
        ])->assertStatus(422);

        $executable = UploadedFile::fake()->create('shell.php', 10, 'application/x-php');
        $this->postJson('/api/v1/media/upload', [
            'mediable_type' => 'vehicle',
            'mediable_id' => $vehicule->id,
            'file' => $executable,
        ])->assertStatus(422);
    }

    public function test_on_ne_depose_pas_de_photo_sur_l_offre_d_un_autre(): void
    {
        Storage::fake('public');
        $victime = Vehicle::factory()->create(['provider_id' => $this->prestataire()->id]);
        Sanctum::actingAs($this->prestataire());

        $this->postJson('/api/v1/media/upload', [
            'mediable_type' => 'vehicle',
            'mediable_id' => $victime->id,
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertForbidden();
    }

    public function test_une_offre_non_publiee_reste_invisible_et_non_reservable(): void
    {
        $enAttente = Vehicle::factory()->create();
        $experience = TourismExperience::factory()->create(); // en attente

        $this->getJson("/api/v1/vehicles/{$enAttente->id}")->assertNotFound();
        $this->getJson("/api/v1/experiences/{$experience->id}")->assertNotFound();

        Sanctum::actingAs(User::factory()->create());
        $this->assertNotSame(201, $this->postJson("/api/v1/vehicles/{$enAttente->id}/bookings", [
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ])->status());
    }

    public function test_un_client_ne_valide_ni_ne_publie_lui_meme_une_offre(): void
    {
        $vehicule = Vehicle::factory()->create();
        Sanctum::actingAs($this->prestataire());

        $this->patchJson("/api/v1/vehicles/{$vehicule->id}/approve")->assertForbidden();
        $this->patchJson('/api/v1/experiences/'.TourismExperience::factory()->create()->id.'/approve')->assertForbidden();
    }
}
