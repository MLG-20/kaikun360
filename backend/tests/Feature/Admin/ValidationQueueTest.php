<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Immo\Models\Property;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Pro\Models\Provider;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests B13.2 : file de validation générique (GET /admin/queue) et décision
 * générique par type (PATCH /admin/validate/{type}/{id}).
 *
 * Couvre l'autorisation (accès back-office + permission fine par type), la
 * normalisation de la file, la préservation des effets de bord métier
 * (conformité véhicule, publication) et les garde-fous (type inconnu, élément
 * déjà traité).
 */
class ValidationQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** Agent disposant de toutes les permissions de validation. */
    private function agent(): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);

        return $agent;
    }

    public function test_un_utilisateur_sans_acces_back_office_est_refuse(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/queue')->assertStatus(403);
    }

    public function test_la_file_donne_une_vue_d_ensemble_par_type(): void
    {
        Property::factory()->count(2)->create(['status' => 'en_attente_validation']);
        Vehicle::factory()->create(['status' => 'en_attente_validation']);
        TourismExperience::factory()->create(['status' => 'en_attente_validation']);
        Provider::factory()->create(['status' => 'en_attente']);

        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/queue')
            ->assertOk()
            ->assertJsonPath('data.total_pending', 5)
            ->assertJsonPath('data.queue.property.count', 2)
            ->assertJsonPath('data.queue.vehicle.count', 1)
            ->assertJsonPath('data.queue.experience.count', 1)
            ->assertJsonPath('data.queue.provider.count', 1)
            ->assertJsonPath('data.queue.property.items.0.type', 'property')
            ->assertJsonStructure([
                'data' => ['queue' => ['property' => ['items' => [['type', 'id', 'label', 'owner_id', 'submitted_at']]]]],
            ]);
    }

    public function test_la_file_filtre_et_pagine_par_type(): void
    {
        Vehicle::factory()->count(3)->create(['status' => 'en_attente_validation']);
        // Un véhicule déjà publié ne doit pas apparaître dans la file.
        Vehicle::factory()->create(['status' => 'publie']);

        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/queue?type=vehicle&per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'vehicle');
    }

    public function test_un_type_inconnu_donne_404(): void
    {
        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/queue?type=banane')->assertStatus(404);
        $this->patchJson('/api/v1/admin/validate/banane/1', ['decision' => 'approve'])->assertStatus(404);
    }

    public function test_validation_generique_publie_un_bien(): void
    {
        $property = Property::factory()->create(['status' => 'en_attente_validation']);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/validate/property/{$property->id}", ['decision' => 'approve'])
            ->assertOk();

        $this->assertDatabaseHas('properties', ['id' => $property->id, 'status' => 'publie']);
    }

    public function test_refus_generique_avec_motif(): void
    {
        $experience = TourismExperience::factory()->create(['status' => 'en_attente_validation']);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/validate/experience/{$experience->id}", [
            'decision' => 'reject',
            'reason' => 'Descriptif insuffisant.',
        ])->assertOk();

        $this->assertDatabaseHas('tourism_experiences', ['id' => $experience->id, 'status' => 'rejete']);
    }

    public function test_validation_vehicule_bloquee_si_conformite_incomplete(): void
    {
        // Véhicule motorisé sans référence d'assurance : conformité incomplète.
        $vehicle = Vehicle::factory()->create([
            'status' => 'en_attente_validation',
            'insurance_ref' => null,
        ]);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/validate/vehicle/{$vehicle->id}", ['decision' => 'approve'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('compliance');

        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'status' => 'en_attente_validation']);
    }

    public function test_on_ne_modere_pas_un_element_deja_traite(): void
    {
        $property = Property::factory()->create(['status' => 'publie']);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/validate/property/{$property->id}", ['decision' => 'approve'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('decision');
    }

    public function test_permission_fine_requise_par_type(): void
    {
        // Utilisateur back-office SANS la permission de valider les biens.
        $user = User::factory()->create();
        $user->givePermissionTo('consulter:dashboard-admin');
        $property = Property::factory()->create(['status' => 'en_attente_validation']);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/admin/validate/property/{$property->id}", ['decision' => 'approve'])
            ->assertStatus(403);
    }
}
