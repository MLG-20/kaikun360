<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Manage\Models\OwnerPayout;
use App\Modules\Stay\Models\Stay;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests B13.5 : export comptable & reporting (GET /admin/reports/export).
 *
 * Vérifie l'accès (gerer:paiements), la consolidation des flux (réservations
 * hors annulées + reversements effectués), le filtre de période et l'export CSV.
 */
class ReportExportTest extends TestCase
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

    private function booking(Stay $stay, string $status, int $amount, int $commission, ?string $when = null): Booking
    {
        $booking = Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => Stay::class,
            'bookable_id' => $stay->id,
            'start_date' => today(),
            'end_date' => today()->addDay(),
            'guests' => 1,
            'amount_xof' => $amount,
            'commission_xof' => $commission,
            'status' => $status,
        ]);

        if ($when !== null) {
            $booking->forceFill(['created_at' => $when])->save();
        }

        return $booking;
    }

    public function test_l_acces_est_reserve_a_gerer_paiements(): void
    {
        // L'agent n'a pas gerer:paiements.
        Sanctum::actingAs($this->withRole(UserRole::AGENT_KAIKUN->value));

        $this->getJson('/api/v1/admin/reports/export')->assertStatus(403);
    }

    public function test_le_rapport_consolide_reservations_et_reversements(): void
    {
        $stay = Stay::factory()->create();
        $this->booking($stay, 'confirmee', 100_000, 12_000);
        $this->booking($stay, 'terminee', 50_000, 6_000);
        $this->booking($stay, 'annulee_client', 999_999, 99_999); // exclue des montants

        OwnerPayout::factory()->create(['status' => 'effectue', 'paid_at' => now(), 'amount_xof' => 40_000]);
        OwnerPayout::factory()->create(['status' => 'en_attente', 'amount_xof' => 999_999]); // non comptée

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->getJson('/api/v1/admin/reports/export')
            ->assertOk()
            ->assertJsonPath('data.summary.bookings_count', 3)
            ->assertJsonPath('data.summary.active_bookings_count', 2)
            ->assertJsonPath('data.summary.gross_volume_xof', 150_000)
            ->assertJsonPath('data.summary.commission_xof', 18_000)
            ->assertJsonPath('data.summary.payouts_count', 1)
            ->assertJsonPath('data.summary.payouts_total_xof', 40_000)
            ->assertJsonCount(3, 'data.bookings')
            ->assertJsonCount(1, 'data.payouts');
    }

    public function test_le_filtre_de_periode_borne_les_reservations(): void
    {
        $stay = Stay::factory()->create();
        $this->booking($stay, 'confirmee', 10_000, 1_200); // aujourd'hui
        $this->booking($stay, 'confirmee', 20_000, 2_400, now()->subMonths(2)->toDateTimeString());

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $from = today()->toDateString();
        $this->getJson("/api/v1/admin/reports/export?from={$from}")
            ->assertOk()
            ->assertJsonPath('data.summary.bookings_count', 1)
            ->assertJsonPath('data.summary.gross_volume_xof', 10_000);
    }

    public function test_l_export_csv_est_telechargeable(): void
    {
        $stay = Stay::factory()->create();
        $this->booking($stay, 'confirmee', 100_000, 12_000);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $response = $this->get('/api/v1/admin/reports/export?format=csv');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('reference,date,type,amount_xof,commission_xof,status', $response->streamedContent());
    }
}
