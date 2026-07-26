<?php

namespace Tests\Feature\Manage;

use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Immo\Models\Property;
use App\Modules\Manage\Models\Incident;
use App\Modules\Manage\Models\ManagementMandate;
use App\Modules\Manage\Models\OwnerPayout;
use App\Modules\Manage\Models\Rent;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests des endpoints de GESTION (agents) du module Manage (phase B4.6) :
 * création/évolution des mandats, loyers, incidents, dépenses, reversements,
 * et application stricte de la permission `gerer:gestion-locative`.
 */
class MandateManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Agent doté de la permission de gestion locative.
     */
    private function agent(): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);
        $agent->givePermissionTo(AdminPermission::operational()); // F7.1.b : agent pleinement outillé (droits désormais délégués, plus portés par le rôle)

        return $agent;
    }

    public function test_un_agent_cree_un_mandat_avec_owner_deduit_du_bien(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($this->agent());

        $this->postJson('/api/v1/manage/mandates', [
            'property_id' => $property->id,
            'commission_rate' => 10,
            'start_date' => '2026-06-01',
        ])
            ->assertCreated()
            ->assertJsonPath('data.mandate.status', 'en_attente');

        $this->assertDatabaseHas('management_mandates', [
            'property_id' => $property->id,
            'owner_id' => $owner->id,
        ]);
    }

    public function test_un_non_agent_ne_peut_pas_creer_de_mandat(): void
    {
        $property = Property::factory()->create();

        Sanctum::actingAs(User::factory()->create()); // simple utilisateur

        $this->postJson('/api/v1/manage/mandates', [
            'property_id' => $property->id,
            'commission_rate' => 10,
            'start_date' => '2026-06-01',
        ])->assertStatus(403);
    }

    public function test_un_agent_ajoute_un_loyer_puis_le_marque_paye(): void
    {
        $mandate = ManagementMandate::factory()->create();
        Sanctum::actingAs($this->agent());

        $rentId = $this->postJson("/api/v1/manage/mandates/{$mandate->id}/rents", [
            'due_date' => '2026-06-01',
            'amount_xof' => 150_000,
            'tenant_name' => 'Awa Diop',
        ])
            ->assertCreated()
            ->assertJsonPath('data.rent.status', 'impaye')
            ->json('data.rent.id');

        $this->patchJson("/api/v1/manage/rents/{$rentId}/pay")
            ->assertOk()
            ->assertJsonPath('data.rent.status', 'paye');

        $this->assertNotNull(Rent::find($rentId)->paid_at);
    }

    public function test_un_agent_signale_un_incident_rattache_au_bien_du_mandat(): void
    {
        $mandate = ManagementMandate::factory()->create();
        $agent = $this->agent();
        Sanctum::actingAs($agent);

        $incidentId = $this->postJson("/api/v1/manage/mandates/{$mandate->id}/incidents", [
            'title' => 'Fuite d\'eau',
            'priority' => 'p2',
        ])
            ->assertCreated()
            ->assertJsonPath('data.incident.status', 'ouvert')
            ->json('data.incident.id');

        $this->assertDatabaseHas('incidents', [
            'id' => $incidentId,
            'property_id' => $mandate->property_id,
            'reported_by' => $agent->id,
        ]);

        $this->patchJson("/api/v1/manage/incidents/{$incidentId}/resolve")
            ->assertOk()
            ->assertJsonPath('data.incident.status', 'resolu');
    }

    public function test_une_depense_est_rattachee_au_bien_du_mandat(): void
    {
        $mandate = ManagementMandate::factory()->create();
        Sanctum::actingAs($this->agent());

        $this->postJson("/api/v1/manage/mandates/{$mandate->id}/expenses", [
            'label' => 'Plomberie',
            'category' => 'reparation',
            'amount_xof' => 45_000,
            'spent_at' => '2026-06-10',
        ])->assertCreated();

        $this->assertDatabaseHas('expenses', [
            'property_id' => $mandate->property_id,
            'label' => 'Plomberie',
        ]);
    }

    public function test_une_depense_refuse_un_incident_d_un_autre_bien(): void
    {
        $mandate = ManagementMandate::factory()->create();
        $autreIncident = Incident::factory()->create(); // bien différent
        Sanctum::actingAs($this->agent());

        $this->postJson("/api/v1/manage/mandates/{$mandate->id}/expenses", [
            'incident_id' => $autreIncident->id,
            'label' => 'Réparation',
            'category' => 'reparation',
            'amount_xof' => 45_000,
            'spent_at' => '2026-06-10',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('incident_id');
    }

    public function test_un_agent_cree_un_reversement_puis_le_marque_effectue(): void
    {
        $mandate = ManagementMandate::factory()->create();
        Sanctum::actingAs($this->agent());

        $payoutId = $this->postJson("/api/v1/manage/mandates/{$mandate->id}/payouts", [
            'period_label' => 'Juin 2026',
            'amount_xof' => 120_000,
        ])
            ->assertCreated()
            ->assertJsonPath('data.payout.status', 'en_attente')
            ->json('data.payout.id');

        $this->patchJson("/api/v1/manage/payouts/{$payoutId}/pay")
            ->assertOk()
            ->assertJsonPath('data.payout.status', 'effectue');

        $this->assertDatabaseHas('owner_payouts', [
            'id' => $payoutId,
            'owner_id' => $mandate->owner_id,
        ]);
        $this->assertNotNull(OwnerPayout::find($payoutId)->paid_at);
    }

    public function test_la_gestion_exige_la_permission(): void
    {
        $mandate = ManagementMandate::factory()->create();
        Sanctum::actingAs(User::factory()->create()); // sans permission

        $this->postJson("/api/v1/manage/mandates/{$mandate->id}/rents", [
            'due_date' => '2026-06-01',
            'amount_xof' => 150_000,
        ])->assertStatus(403);
    }
}
