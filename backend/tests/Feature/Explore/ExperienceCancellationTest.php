<?php

namespace Tests\Feature\Explore;

use App\Models\Booking;
use App\Models\User;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Explore\Services\ExperienceBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests d'annulation de réservation d'expérience (phase B6.4) : éligibilité au
 * remboursement selon le délai, autorisations et libération des places.
 */
class ExperienceCancellationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crée une réservation d'expérience pour `$user` avec un départ dans `$daysAhead` jours.
     */
    private function booking(User $user, int $daysAhead, int $guests = 2, string $status = 'confirmee'): Booking
    {
        $experience = TourismExperience::factory()->published()->create(['capacity' => 10]);

        return Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => $user->id,
            'bookable_type' => TourismExperience::class,
            'bookable_id' => $experience->id,
            'start_date' => now()->addDays($daysAhead)->toDateString(),
            'end_date' => now()->addDays($daysAhead + 2)->toDateString(),
            'guests' => $guests,
            'amount_xof' => 150_000,
            'status' => $status,
        ]);
    }

    public function test_annulation_dans_les_delais_ouvre_droit_au_remboursement(): void
    {
        $client = User::factory()->create();
        $booking = $this->booking($client, daysAhead: 10);

        Sanctum::actingAs($client);

        $this->patchJson("/api/v1/experiences/bookings/{$booking->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.booking.status', 'annulee_client')
            ->assertJsonPath('data.refund.eligible', true)
            ->assertJsonPath('data.refund.amount_xof', 150_000);
    }

    public function test_annulation_tardive_n_ouvre_pas_droit_au_remboursement(): void
    {
        $client = User::factory()->create();
        $booking = $this->booking($client, daysAhead: 2);

        Sanctum::actingAs($client);

        $this->patchJson("/api/v1/experiences/bookings/{$booking->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.refund.eligible', false)
            ->assertJsonPath('data.refund.amount_xof', 0);
    }

    public function test_un_tiers_ne_peut_pas_annuler_la_reservation(): void
    {
        $client = User::factory()->create();
        $booking = $this->booking($client, daysAhead: 10);

        Sanctum::actingAs(User::factory()->create()); // autre utilisateur

        $this->patchJson("/api/v1/experiences/bookings/{$booking->id}/cancel")->assertStatus(403);
    }

    public function test_une_reservation_deja_annulee_ne_peut_pas_l_etre_a_nouveau(): void
    {
        $client = User::factory()->create();
        $booking = $this->booking($client, daysAhead: 10, status: 'annulee_client');

        Sanctum::actingAs($client);

        $this->patchJson("/api/v1/experiences/bookings/{$booking->id}/cancel")
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_l_annulation_libere_les_places(): void
    {
        $client = User::factory()->create();
        $booking = $this->booking($client, daysAhead: 10, guests: 4);
        $experience = $booking->bookable;

        $this->assertSame(6, app(ExperienceBookingService::class)->seatsLeft($experience));

        Sanctum::actingAs($client);
        $this->patchJson("/api/v1/experiences/bookings/{$booking->id}/cancel")->assertOk();

        $this->assertSame(10, app(ExperienceBookingService::class)->seatsLeft($experience->fresh()));
    }
}
