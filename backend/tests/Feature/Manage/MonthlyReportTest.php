<?php

namespace Tests\Feature\Manage;

use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Manage\Models\Expense;
use App\Modules\Manage\Models\ManagementMandate;
use App\Modules\Manage\Models\Rent;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests du rapport mensuel de gestion locative (phase B4.6) : exactitude des
 * agrégats (commission, net propriétaire) et bornage par mois calendaire.
 */
class MonthlyReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_le_rapport_calcule_commission_et_net_proprietaire(): void
    {
        $owner = User::factory()->create();
        $mandate = ManagementMandate::factory()->create([
            'owner_id' => $owner->id,
            'commission_rate' => 10,
        ]);

        // Loyers de juin 2026.
        Rent::factory()->paid()->create([
            'mandate_id' => $mandate->id, 'amount_xof' => 200_000, 'due_date' => '2026-06-05',
        ]);
        Rent::factory()->create([
            'mandate_id' => $mandate->id, 'amount_xof' => 50_000, 'status' => 'impaye', 'due_date' => '2026-06-05',
        ]);
        // Loyer d'un autre mois (ne doit PAS compter).
        Rent::factory()->paid()->create([
            'mandate_id' => $mandate->id, 'amount_xof' => 999_000, 'due_date' => '2026-05-05',
        ]);

        Expense::factory()->create([
            'property_id' => $mandate->property_id, 'amount_xof' => 30_000, 'spent_at' => '2026-06-12',
        ]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/manage/mandates/{$mandate->id}/report?month=2026-06")
            ->assertOk()
            ->assertJsonPath('data.report.rents.paid_xof', 200_000)
            ->assertJsonPath('data.report.rents.unpaid_xof', 50_000)
            ->assertJsonPath('data.report.expenses.total_xof', 30_000)
            // Commission = 10 % de 200 000 = 20 000.
            ->assertJsonPath('data.report.commission_xof', 20_000)
            // Net = 200 000 − 20 000 − 30 000 = 150 000.
            ->assertJsonPath('data.report.net_owner_xof', 150_000)
            ->assertJsonPath('data.report.period.month', '2026-06');
    }

    public function test_un_proprietaire_ne_voit_pas_le_rapport_d_un_autre(): void
    {
        $mandate = ManagementMandate::factory()->create(); // appartient à un autre
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/manage/mandates/{$mandate->id}/report")->assertStatus(403);
    }

    public function test_un_agent_peut_consulter_le_rapport(): void
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);
        $agent->givePermissionTo(AdminPermission::operational()); // F7.1.b : agent pleinement outillé (droits désormais délégués, plus portés par le rôle)
        $mandate = ManagementMandate::factory()->create();

        Sanctum::actingAs($agent);

        $this->getJson("/api/v1/manage/mandates/{$mandate->id}/report")->assertOk();
    }

    public function test_le_mois_invalide_est_rejete(): void
    {
        $owner = User::factory()->create();
        $mandate = ManagementMandate::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/manage/mandates/{$mandate->id}/report?month=2026")
            ->assertStatus(422)
            ->assertJsonValidationErrors('month');
    }
}
