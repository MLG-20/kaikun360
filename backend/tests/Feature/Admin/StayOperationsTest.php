<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Stay\Models\Stay;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests B13.6 : exploitation des nuitées back-office (calendrier, check-in /
 * check-out, ménage). Réservé à `gerer:nuitees` et aux réservations Stay.
 */
class StayOperationsTest extends TestCase
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

    private function stayBooking(): Booking
    {
        $stay = Stay::factory()->create();

        return Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => Stay::class,
            'bookable_id' => $stay->id,
            'start_date' => today(),
            'end_date' => today()->addDays(2),
            'guests' => 2,
            'amount_xof' => 50_000,
            'status' => 'confirmee',
        ]);
    }

    public function test_un_utilisateur_sans_gerer_nuitees_est_refuse(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/stays/calendar')->assertStatus(403);
    }

    public function test_le_calendrier_ne_liste_que_les_nuitees(): void
    {
        $stayBooking = $this->stayBooking();

        // Une réservation de véhicule ne doit pas apparaître dans le calendrier.
        $vehicle = Vehicle::factory()->create();
        Booking::create([
            'reference' => 'BK-VEH-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => Vehicle::class,
            'bookable_id' => $vehicle->id,
            'start_date' => today(),
            'end_date' => today()->addDay(),
            'guests' => 1,
            'amount_xof' => 30_000,
            'status' => 'confirmee',
        ]);

        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/stays/calendar')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.booking_id', $stayBooking->id);
    }

    public function test_cycle_check_in_check_out_et_menage(): void
    {
        $booking = $this->stayBooking();
        Sanctum::actingAs($this->agent());

        // Check-out impossible avant l'arrivée.
        $this->patchJson("/api/v1/admin/stay-bookings/{$booking->id}/check-out")
            ->assertStatus(422);

        // Arrivée.
        $this->patchJson("/api/v1/admin/stay-bookings/{$booking->id}/check-in")
            ->assertOk()
            ->assertJsonPath('data.booking.checked_in_at', fn ($v) => $v !== null);

        // Double arrivée refusée.
        $this->patchJson("/api/v1/admin/stay-bookings/{$booking->id}/check-in")
            ->assertStatus(422);

        // Départ → ménage à faire.
        $this->patchJson("/api/v1/admin/stay-bookings/{$booking->id}/check-out")
            ->assertOk()
            ->assertJsonPath('data.booking.housekeeping_status', 'a_faire');

        // Suivi du ménage.
        $this->patchJson("/api/v1/admin/stay-bookings/{$booking->id}/housekeeping", ['status' => 'fait'])
            ->assertOk()
            ->assertJsonPath('data.booking.housekeeping_status', 'fait');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'housekeeping_status' => 'fait',
        ]);
    }

    public function test_statut_menage_invalide_refuse(): void
    {
        $booking = $this->stayBooking();
        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/stay-bookings/{$booking->id}/housekeeping", ['status' => 'brille'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_operation_refusee_sur_une_reservation_non_nuitee(): void
    {
        $vehicle = Vehicle::factory()->create();
        $booking = Booking::create([
            'reference' => 'BK-VEH-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => Vehicle::class,
            'bookable_id' => $vehicle->id,
            'start_date' => today(),
            'end_date' => today()->addDay(),
            'guests' => 1,
            'amount_xof' => 30_000,
            'status' => 'confirmee',
        ]);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/stay-bookings/{$booking->id}/check-in")
            ->assertStatus(422);
    }
}
