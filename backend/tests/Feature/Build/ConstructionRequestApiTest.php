<?php

namespace Tests\Feature\Build;

use App\Models\Report;
use App\Models\User;
use App\Modules\Build\Enums\ConstructionObjective;
use App\Modules\Build\Enums\FinishLevel;
use App\Modules\Build\Models\ConstructionRequest;
use App\Modules\Core\Enums\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests d'API du module Build (phase B5.5) : dépôt de demande (estimation +
 * jalons), isolation par client (policy), rapports de suivi et simulation.
 */
class ConstructionRequestApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function agent(): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);

        return $agent;
    }

    public function test_un_client_depose_une_demande_avec_estimation_et_jalons(): void
    {
        $client = User::factory()->create();
        Sanctum::actingAs($client);

        $response = $this->postJson('/api/v1/construction-requests', [
            'objective' => ConstructionObjective::CONSTRUCTION_NEUVE->value,
            'city' => 'Dakar',
            'surface_m2' => 100,
            'finish_level' => FinishLevel::STANDARD->value,
            'description' => 'Villa R+1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.construction_request.status', 'soumise')
            // 250 000 × 100 × 1.0 = 25 000 000.
            ->assertJsonPath('data.construction_request.estimated_cost_xof', 25_000_000)
            ->assertJsonCount(7, 'data.construction_request.milestones');

        $this->assertDatabaseHas('construction_requests', [
            'client_id' => $client->id,
            'city' => 'Dakar',
        ]);
    }

    public function test_le_depot_exige_une_authentification(): void
    {
        $this->postJson('/api/v1/construction-requests', [])->assertStatus(401);
    }

    public function test_le_depot_valide_les_champs(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/construction-requests', [
            'objective' => 'inexistant',
            'surface_m2' => 0,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['objective', 'surface_m2', 'city', 'finish_level']);
    }

    public function test_mine_ne_liste_que_mes_demandes(): void
    {
        $client = User::factory()->create();
        ConstructionRequest::factory()->count(2)->create(['client_id' => $client->id]);
        ConstructionRequest::factory()->create(); // autre client

        Sanctum::actingAs($client);

        $this->getJson('/api/v1/construction-requests/mine')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_un_client_ne_voit_pas_la_demande_d_un_autre(): void
    {
        $request = ConstructionRequest::factory()->create(); // autre client
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/construction-requests/{$request->id}")->assertStatus(403);
    }

    public function test_un_agent_peut_voir_n_importe_quelle_demande(): void
    {
        $request = ConstructionRequest::factory()->create();
        Sanctum::actingAs($this->agent());

        $this->getJson("/api/v1/construction-requests/{$request->id}")
            ->assertOk()
            ->assertJsonPath('data.construction_request.id', $request->id);
    }

    public function test_le_client_consulte_les_rapports_de_sa_demande(): void
    {
        $client = User::factory()->create();
        $request = ConstructionRequest::factory()->create(['client_id' => $client->id]);
        Report::factory()->count(3)->create([
            'reportable_type' => ConstructionRequest::class,
            'reportable_id' => $request->id,
        ]);

        Sanctum::actingAs($client);

        $this->getJson("/api/v1/construction-requests/{$request->id}/reports")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_un_agent_publie_un_rapport_mais_pas_un_client(): void
    {
        $client = User::factory()->create();
        $request = ConstructionRequest::factory()->create(['client_id' => $client->id]);

        // Le client n'a pas la permission gerer:chantiers.
        Sanctum::actingAs($client);
        $this->postJson("/api/v1/construction-requests/{$request->id}/reports", [
            'type' => 'photo',
            'reported_at' => '2026-06-20',
        ])->assertStatus(403);

        // L'agent, oui.
        $agent = $this->agent();
        Sanctum::actingAs($agent);
        $this->postJson("/api/v1/construction-requests/{$request->id}/reports", [
            'type' => 'photo',
            'photos' => ['suivi/a.jpg'],
            'comment' => 'Coulage dalle',
            'reported_at' => '2026-06-20',
        ])
            ->assertCreated()
            ->assertJsonPath('data.report.type', 'photo');

        $this->assertDatabaseHas('reports', [
            'reportable_id' => $request->id,
            'created_by' => $agent->id,
        ]);
    }

    public function test_la_simulation_renvoie_une_estimation(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/construction-requests/simulate', [
            'objective' => ConstructionObjective::RENOVATION->value,
            'surface_m2' => 80,
            'finish_level' => FinishLevel::ECONOMIQUE->value,
        ])
            ->assertOk()
            // 150 000 × 80 × 0.85 = 10 200 000.
            ->assertJsonPath('data.simulation.estimated_cost_xof', 10_200_000);
    }
}
