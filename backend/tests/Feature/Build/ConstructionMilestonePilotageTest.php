<?php

namespace Tests\Feature\Build;

use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Build\Enums\MilestoneStatus;
use App\Modules\Build\Models\ConstructionMilestone;
use App\Modules\Build\Models\ConstructionRequest;
use App\Modules\Core\Enums\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests du PILOTAGE des jalons de chantier (phase F7.3.e1).
 *
 * Les jalons existaient depuis B5.3 mais restaient figés après le dépôt : aucun
 * endpoint ne permettait de les faire avancer ni de replanifier le chantier,
 * alors que « jalons chantier » est une fonction du CDC §6. Ces tests couvrent
 * les quatre gestes ajoutés (ajouter, faire avancer, réordonner, retirer) et les
 * garde-fous : permission `gerer:chantiers`, cohérence statut ↔ date réelle,
 * refus d'un ordre mêlant un jalon étranger au chantier.
 */
class ConstructionMilestonePilotageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** Agent pleinement outillé (droits délégués par personne depuis F7.1.b). */
    private function agent(): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);
        $agent->givePermissionTo(AdminPermission::operational());

        return $agent;
    }

    public function test_un_agent_ajoute_un_jalon_en_fin_de_planning(): void
    {
        $request = ConstructionRequest::factory()->create();
        ConstructionMilestone::factory()->create([
            'construction_request_id' => $request->id,
            'position' => 4,
        ]);

        Sanctum::actingAs($this->agent());

        $response = $this->postJson("/api/v1/construction-requests/{$request->id}/milestones", [
            'name' => 'Pose de la charpente',
            'planned_date' => '2026-09-15',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.milestone.name', 'Pose de la charpente')
            ->assertJsonPath('data.milestone.status', MilestoneStatus::A_VENIR->value)
            // Position omise → ajouté après le dernier jalon (4 + 1).
            ->assertJsonPath('data.milestone.position', 5);
    }

    public function test_terminer_un_jalon_sans_date_le_date_du_jour(): void
    {
        $milestone = ConstructionMilestone::factory()->create([
            'status' => MilestoneStatus::EN_COURS->value,
            'actual_date' => null,
        ]);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/construction-milestones/{$milestone->id}", [
            'status' => MilestoneStatus::TERMINE->value,
        ])->assertOk()->assertJsonPath('data.milestone.status', MilestoneStatus::TERMINE->value);

        $this->assertSame(
            now()->toDateString(),
            $milestone->fresh()->actual_date?->toDateString()
        );
    }

    public function test_rouvrir_un_jalon_effacé_sa_date_de_realisation(): void
    {
        $milestone = ConstructionMilestone::factory()->done()->create();
        $this->assertNotNull($milestone->actual_date);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/construction-milestones/{$milestone->id}", [
            'status' => MilestoneStatus::EN_COURS->value,
        ])->assertOk();

        // Sans cet effacement, l'écran afficherait une étape en cours « achevée le … ».
        $this->assertNull($milestone->fresh()->actual_date);
    }

    public function test_une_date_reelle_explicite_est_respectee(): void
    {
        $milestone = ConstructionMilestone::factory()->create([
            'status' => MilestoneStatus::EN_COURS->value,
            'actual_date' => null,
        ]);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/construction-milestones/{$milestone->id}", [
            'status' => MilestoneStatus::TERMINE->value,
            'actual_date' => '2026-07-10',
        ])->assertOk();

        $this->assertSame('2026-07-10', $milestone->fresh()->actual_date?->toDateString());
    }

    public function test_un_patch_vide_est_refuse(): void
    {
        $milestone = ConstructionMilestone::factory()->create();

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/construction-milestones/{$milestone->id}", [])
            ->assertStatus(422);
    }

    public function test_le_planning_se_reordonne(): void
    {
        $request = ConstructionRequest::factory()->create();
        $premier = ConstructionMilestone::factory()->create([
            'construction_request_id' => $request->id,
            'position' => 1,
        ]);
        $second = ConstructionMilestone::factory()->create([
            'construction_request_id' => $request->id,
            'position' => 2,
        ]);

        Sanctum::actingAs($this->agent());

        $this->putJson("/api/v1/construction-requests/{$request->id}/milestones/reorder", [
            'milestones' => [$second->id, $premier->id],
        ])->assertOk()->assertJsonCount(2, 'data.milestones');

        $this->assertSame(1, $second->fresh()->position);
        $this->assertSame(2, $premier->fresh()->position);
    }

    public function test_un_ordre_melant_un_jalon_etranger_est_refuse_en_entier(): void
    {
        $request = ConstructionRequest::factory()->create();
        $sien = ConstructionMilestone::factory()->create([
            'construction_request_id' => $request->id,
            'position' => 1,
        ]);
        // Jalon d'un AUTRE chantier.
        $etranger = ConstructionMilestone::factory()->create(['position' => 1]);

        Sanctum::actingAs($this->agent());

        $this->putJson("/api/v1/construction-requests/{$request->id}/milestones/reorder", [
            'milestones' => [$etranger->id, $sien->id],
        ])->assertStatus(422);

        // Rien n'a bougé : l'ordre partiel est refusé en bloc.
        $this->assertSame(1, $sien->fresh()->position);
    }

    public function test_un_agent_retire_un_jalon(): void
    {
        $milestone = ConstructionMilestone::factory()->create();

        Sanctum::actingAs($this->agent());

        $this->deleteJson("/api/v1/construction-milestones/{$milestone->id}")->assertOk();

        $this->assertDatabaseMissing('construction_milestones', ['id' => $milestone->id]);
    }

    public function test_un_client_ne_peut_pas_piloter_les_jalons(): void
    {
        $request = ConstructionRequest::factory()->create();
        $milestone = ConstructionMilestone::factory()->create([
            'construction_request_id' => $request->id,
        ]);

        // Le client propriétaire du dossier : il le CONSULTE (policy view) mais ne
        // pilote pas le chantier.
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/construction-requests/{$request->id}/milestones", [
            'name' => 'Étape ajoutée par le client',
        ])->assertForbidden();

        $this->patchJson("/api/v1/construction-milestones/{$milestone->id}", [
            'status' => MilestoneStatus::TERMINE->value,
        ])->assertForbidden();

        $this->deleteJson("/api/v1/construction-milestones/{$milestone->id}")->assertForbidden();
    }
}
