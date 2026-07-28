<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Build\Models\ConstructionRequest;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Manage\Models\ManagementMandate;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests B13.7.2 : vues back-office des dossiers (construction, gestion locative).
 */
class AdminDossierTest extends TestCase
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

    public function test_acces_refuse_sans_dashboard_admin(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/construction-requests')->assertStatus(403);
        $this->getJson('/api/v1/admin/mandates')->assertStatus(403);
    }

    public function test_liste_toutes_les_demandes_de_construction(): void
    {
        ConstructionRequest::factory()->count(3)->create();

        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/construction-requests')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            // Compteurs de supervision surfacés pour la liste back-office.
            ->assertJsonStructure([
                'data' => [['reference', 'status', 'reports_count', 'milestones_count']],
            ]);
    }

    public function test_liste_tous_les_mandats_de_gestion_locative(): void
    {
        ManagementMandate::factory()->count(2)->create();

        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/mandates')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            // Compteurs bruts (rents/incidents/expenses/payouts) surfacés pour la
            // supervision, en plus du bloc `summary`.
            ->assertJsonStructure([
                'data' => [[
                    'reference', 'status', 'summary',
                    'rents_count', 'incidents_count', 'expenses_count', 'payouts_count',
                ]],
            ]);
    }
}
