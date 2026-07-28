<?php

namespace Tests\Feature\TeamBuilding;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Pro\Enums\ProviderStatus;
use App\Modules\Pro\Models\Provider;
use App\Modules\TeamBuilding\Models\TeamBuildingRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests de l'« affectation prestataires » du team building (F7.2.h — CDC §6).
 *
 * Le back-office affecte un prestataire validé à une brique du pack ; chaque
 * affectation crée une mission Pro rattachée à la demande (cycle standard,
 * commission figée), exposée dans la fiche.
 */
class TeamBuildingAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::ADMIN->value);

        return $admin;
    }

    public function test_un_admin_affecte_un_prestataire_valide_a_une_demande(): void
    {
        $request = TeamBuildingRequest::factory()->create();
        $provider = Provider::factory()->validated()->create();

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/team-building-requests/{$request->id}/assignments", [
            'provider_id' => $provider->id,
            'category' => 'hebergement',
            'amount_xof' => 500_000,
        ])
            ->assertCreated()
            ->assertJsonPath('data.mission.category', 'hebergement')
            ->assertJsonPath('data.mission.status', 'affectee')
            ->assertJsonPath('data.mission.provider.business_name', $provider->business_name);

        $this->assertDatabaseHas('provider_missions', [
            'team_building_request_id' => $request->id,
            'provider_id' => $provider->id,
            'category' => 'hebergement',
            'client_id' => $request->company_id,
            'status' => 'affectee',
        ]);
    }

    public function test_l_affectation_est_refusee_a_une_entreprise(): void
    {
        $request = TeamBuildingRequest::factory()->create();
        $provider = Provider::factory()->validated()->create();

        Sanctum::actingAs(User::find($request->company_id));

        $this->postJson("/api/v1/team-building-requests/{$request->id}/assignments", [
            'provider_id' => $provider->id,
            'category' => 'mobilite',
            'amount_xof' => 100_000,
        ])->assertStatus(403);
    }

    public function test_on_ne_peut_pas_affecter_un_prestataire_non_valide(): void
    {
        $request = TeamBuildingRequest::factory()->create();
        $provider = Provider::factory()->create(['status' => ProviderStatus::EN_ATTENTE->value]);

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/team-building-requests/{$request->id}/assignments", [
            'provider_id' => $provider->id,
            'category' => 'animation',
            'amount_xof' => 100_000,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('provider_id');
    }

    public function test_une_categorie_de_pack_invalide_est_rejetee(): void
    {
        $request = TeamBuildingRequest::factory()->create();
        $provider = Provider::factory()->validated()->create();

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/team-building-requests/{$request->id}/assignments", [
            'provider_id' => $provider->id,
            'category' => 'inexistant',
            'amount_xof' => 100_000,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('category');
    }

    public function test_la_fiche_expose_les_prestataires_affectes(): void
    {
        $request = TeamBuildingRequest::factory()->create();
        $provider = Provider::factory()->validated()->create();

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/team-building-requests/{$request->id}/assignments", [
            'provider_id' => $provider->id,
            'category' => 'restauration',
            'amount_xof' => 300_000,
        ])->assertCreated();

        $this->getJson("/api/v1/team-building-requests/{$request->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.request.provider_missions')
            ->assertJsonPath('data.request.provider_missions.0.category', 'restauration')
            ->assertJsonPath('data.request.provider_missions.0.provider.business_name', $provider->business_name);
    }
}
