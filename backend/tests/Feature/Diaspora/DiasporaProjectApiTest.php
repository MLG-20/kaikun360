<?php

namespace Tests\Feature\Diaspora;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Diaspora\Enums\DiasporaPriority;
use App\Modules\Diaspora\Models\DiasporaProject;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests d'API du module Diaspora (phase B8.2) : dépôt, isolation par client,
 * affectation d'agent, rapports et priorisation back-office.
 */
class DiasporaProjectApiTest extends TestCase
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

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::ADMIN->value);

        return $admin;
    }

    public function test_un_client_depose_un_projet(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/diaspora-projects', [
            'project_type' => 'construction',
            'residence_country' => 'France',
            'budget_xof' => 40_000_000,
            'priority' => 'haute',
        ])
            ->assertCreated()
            ->assertJsonPath('data.project.status', 'nouveau')
            ->assertJsonPath('data.project.priority', 'haute');
    }

    public function test_mine_ne_liste_que_mes_projets(): void
    {
        $client = User::factory()->create();
        DiasporaProject::factory()->count(2)->create(['client_id' => $client->id]);
        DiasporaProject::factory()->create();

        Sanctum::actingAs($client);

        $this->getJson('/api/v1/diaspora-projects/mine')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_un_tiers_ne_voit_pas_le_projet_d_un_autre(): void
    {
        $project = DiasporaProject::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/diaspora-projects/{$project->id}")->assertStatus(403);
    }

    public function test_l_agent_affecte_voit_le_projet(): void
    {
        $agent = $this->agent();
        $project = DiasporaProject::factory()->assigned($agent)->create();

        Sanctum::actingAs($agent);

        $this->getJson("/api/v1/diaspora-projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.project.id', $project->id);
    }

    public function test_un_admin_affecte_un_agent_explicitement(): void
    {
        $project = DiasporaProject::factory()->create();
        $agent = $this->agent();

        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/v1/diaspora-projects/{$project->id}/assign", ['agent_id' => $agent->id])
            ->assertOk()
            ->assertJsonPath('data.project.agent_id', $agent->id)
            ->assertJsonPath('data.project.status', 'en_cours');
    }

    public function test_l_affectation_auto_choisit_l_agent_le_moins_charge(): void
    {
        $busy = $this->agent();
        $free = $this->agent();
        // L'agent "busy" suit déjà un projet actif.
        DiasporaProject::factory()->assigned($busy)->create();

        $project = DiasporaProject::factory()->create();

        Sanctum::actingAs($this->admin());
        $this->patchJson("/api/v1/diaspora-projects/{$project->id}/assign")
            ->assertOk()
            ->assertJsonPath('data.project.agent_id', $free->id);
    }

    public function test_un_non_admin_ne_peut_pas_affecter(): void
    {
        $project = DiasporaProject::factory()->create();

        Sanctum::actingAs($this->agent()); // agent, pas admin

        $this->patchJson("/api/v1/diaspora-projects/{$project->id}/assign")->assertStatus(403);
    }

    public function test_l_agent_affecte_ajoute_un_rapport_mais_pas_le_client(): void
    {
        $agent = $this->agent();
        $project = DiasporaProject::factory()->assigned($agent)->create();

        // Le client propriétaire ne peut pas écrire (lecture seule).
        Sanctum::actingAs(User::find($project->client_id));
        $this->postJson("/api/v1/diaspora-projects/{$project->id}/reports", [
            'type' => 'photo', 'reported_at' => '2026-06-25',
        ])->assertStatus(403);

        // L'agent affecté, oui.
        Sanctum::actingAs($agent);
        $this->postJson("/api/v1/diaspora-projects/{$project->id}/reports", [
            'type' => 'photo',
            'photos' => ['suivi/a.jpg'],
            'reported_at' => '2026-06-25',
        ])
            ->assertCreated()
            ->assertJsonPath('data.report.type', 'photo');

        $this->assertDatabaseHas('reports', [
            'reportable_id' => $project->id,
            'reportable_type' => DiasporaProject::class,
            'created_by' => $agent->id,
        ]);
    }

    public function test_la_vue_back_office_priorise_les_dossiers_a_forte_valeur(): void
    {
        DiasporaProject::factory()->create(['priority' => DiasporaPriority::NORMALE->value]);
        $strategique = DiasporaProject::factory()->strategic()->create();

        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/diaspora-projects')
            ->assertOk()
            ->assertJsonPath('data.0.id', $strategique->id)
            ->assertJsonPath('data.0.priority', 'strategique');
    }

    public function test_la_vue_back_office_est_refusee_a_un_client(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/diaspora-projects')->assertStatus(403);
    }
}
