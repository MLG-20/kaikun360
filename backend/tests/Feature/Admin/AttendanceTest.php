<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Admin\Models\Attendance;
use App\Modules\Core\Enums\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests F7.1.c : pointeuse de l'équipe back-office.
 *
 * Couvre le périmètre PERSONNEL (pointer entrée/sortie pour soi, consulter son
 * propre pointage, garde-fous de cohérence) et le périmètre SUPERVISION (feuille
 * de présence mensuelle réservée à l'administrateur, détail + récapitulatif +
 * export CSV).
 */
class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_un_agent_pointe_son_entree_puis_sa_sortie(): void
    {
        $agent = $this->withRole(UserRole::AGENT_KAIKUN->value);
        Sanctum::actingAs($agent);

        $this->postJson('/api/v1/admin/attendance/clock-in')
            ->assertCreated()
            ->assertJsonPath('data.attendance.is_open', true);

        $this->assertDatabaseHas('attendances', ['user_id' => $agent->id, 'ended_at' => null]);

        $this->postJson('/api/v1/admin/attendance/clock-out')
            ->assertOk()
            ->assertJsonPath('data.attendance.is_open', false)
            ->assertJsonPath('data.attendance.duration_minutes', 0);

        $this->assertDatabaseMissing('attendances', ['user_id' => $agent->id, 'ended_at' => null]);
    }

    public function test_deux_entrees_consecutives_sont_refusees(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::AGENT_KAIKUN->value));

        $this->postJson('/api/v1/admin/attendance/clock-in')->assertCreated();
        $this->postJson('/api/v1/admin/attendance/clock-in')->assertStatus(422);
    }

    public function test_une_sortie_sans_entree_est_refusee(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::AGENT_KAIKUN->value));

        $this->postJson('/api/v1/admin/attendance/clock-out')->assertStatus(422);
    }

    public function test_me_montre_mon_etat_courant(): void
    {
        $agent = $this->withRole(UserRole::AGENT_KAIKUN->value);
        Sanctum::actingAs($agent);

        $this->getJson('/api/v1/admin/attendance/me')
            ->assertOk()
            ->assertJsonPath('data.on_duty', false);

        $this->postJson('/api/v1/admin/attendance/clock-in')->assertCreated();

        $this->getJson('/api/v1/admin/attendance/me')
            ->assertOk()
            ->assertJsonPath('data.on_duty', true)
            ->assertJsonPath('data.current.is_open', true);
    }

    public function test_un_compte_hors_back_office_ne_pointe_pas(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::CLIENT->value));

        $this->postJson('/api/v1/admin/attendance/clock-in')->assertStatus(403);
    }

    public function test_un_agent_ne_voit_pas_la_feuille_de_l_equipe(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::AGENT_KAIKUN->value));

        $this->getJson('/api/v1/admin/attendance')->assertStatus(403);
    }

    public function test_l_admin_consulte_le_recapitulatif_de_l_equipe(): void
    {
        $agent = $this->withRole(UserRole::AGENT_KAIKUN->value);
        // Une session soldée de 2 h ce mois-ci.
        Attendance::create([
            'user_id' => $agent->id,
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHours(1),
        ]);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->getJson('/api/v1/admin/attendance')
            ->assertOk()
            ->assertJsonPath('data.employees.0.user.id', $agent->id)
            ->assertJsonPath('data.employees.0.total_minutes', 120)
            ->assertJsonPath('data.employees.0.days_present', 1);
    }

    public function test_l_admin_consulte_le_detail_d_un_employe(): void
    {
        $agent = $this->withRole(UserRole::AGENT_KAIKUN->value);
        Attendance::create([
            'user_id' => $agent->id,
            'started_at' => now()->subHours(2),
            'ended_at' => now(),
        ]);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $month = now()->format('Y-m');
        $this->getJson("/api/v1/admin/attendance?user={$agent->id}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.user.id', $agent->id)
            ->assertJsonPath('data.total_minutes', 120)
            ->assertJsonCount(1, 'data.days');
    }

    public function test_l_admin_exporte_la_feuille_en_csv(): void
    {
        $agent = $this->withRole(UserRole::AGENT_KAIKUN->value);
        Attendance::create([
            'user_id' => $agent->id,
            'started_at' => now()->subHours(2),
            'ended_at' => now(),
        ]);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $response = $this->get('/api/v1/admin/attendance?format=csv');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }
}
