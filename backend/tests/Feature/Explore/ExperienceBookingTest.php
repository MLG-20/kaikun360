<?php

namespace Tests\Feature\Explore;

use App\Models\Booking;
use App\Models\User;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Explore\Services\ExperienceBookingService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests de réservation et de capacité des expériences (phase B6.3) :
 * panier groupe, places restantes et refus de dépassement de capacité.
 */
class ExperienceBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_places_restantes_tiennent_compte_des_groupes(): void
    {
        $experience = TourismExperience::factory()->published()->create(['capacity' => 10]);
        $this->seatBooking($experience, 3);
        $this->seatBooking($experience, 2);

        $this->getJson("/api/v1/experiences/{$experience->id}/availability")
            ->assertOk()
            ->assertJsonPath('data.seats_left', 5);
    }

    public function test_un_client_reserve_des_places_en_groupe(): void
    {
        $experience = TourismExperience::factory()->published()->create([
            'capacity' => 10, 'price_xof' => 50_000, 'duration_days' => 3,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/experiences/{$experience->id}/bookings", [
            'guests' => 4,
            'start_date' => now()->addWeek()->toDateString(),
        ])
            ->assertCreated()
            // 4 × 50 000 = 200 000.
            ->assertJsonPath('data.booking.amount_xof', 200_000)
            ->assertJsonPath('data.booking.guests', 4);

        $this->assertSame(6, app(ExperienceBookingService::class)->seatsLeft($experience->fresh()));
    }

    /**
     * F8.4 — la commission plateforme est FIGÉE à la réservation.
     *
     * Le tourisme ne l'enregistrait pas : la plateforme vendait des circuits
     * sans aucune trace de son revenu dans l'export comptable.
     */
    public function test_la_commission_plateforme_est_calculee_et_figee(): void
    {
        $experience = TourismExperience::factory()->published()->create([
            'capacity' => 10, 'price_xof' => 50_000, 'duration_days' => 3,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/experiences/{$experience->id}/bookings", [
            'guests' => 4,
            'start_date' => now()->addWeek()->toDateString(),
        ])->assertCreated();

        // 12 % (taux de repli) de 200 000 = 24 000.
        $this->assertDatabaseHas('bookings', ['amount_xof' => 200_000, 'commission_xof' => 24_000]);
    }

    public function test_le_taux_de_commission_suit_le_reglage_du_back_office(): void
    {
        // Rien n'est codé en dur : la direction fixe le taux depuis les Paramètres.
        Settings::set('commission.default_rate', 8.0);

        $experience = TourismExperience::factory()->published()->create([
            'capacity' => 10, 'price_xof' => 50_000, 'duration_days' => 3,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/experiences/{$experience->id}/bookings", [
            'guests' => 2,
            'start_date' => now()->addWeek()->toDateString(),
        ])->assertCreated();

        $this->assertDatabaseHas('bookings', ['commission_xof' => 8_000]); // 8 % de 100 000
    }

    public function test_la_reservation_refuse_le_depassement_de_capacite(): void
    {
        $experience = TourismExperience::factory()->published()->create(['capacity' => 5]);
        $this->seatBooking($experience, 4);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/experiences/{$experience->id}/bookings", [
            'guests' => 2, // 4 + 2 > 5
            'start_date' => now()->addWeek()->toDateString(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('guests');
    }

    public function test_la_reservation_exige_une_authentification(): void
    {
        $experience = TourismExperience::factory()->published()->create();

        $this->postJson("/api/v1/experiences/{$experience->id}/bookings", [
            'guests' => 1,
            'start_date' => now()->addWeek()->toDateString(),
        ])->assertStatus(401);
    }

    public function test_une_experience_non_publiee_n_est_pas_reservable(): void
    {
        $experience = TourismExperience::factory()->create(); // en attente

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/experiences/{$experience->id}/bookings", [
            'guests' => 1,
            'start_date' => now()->addWeek()->toDateString(),
        ])->assertStatus(404);
    }

    public function test_une_annulation_libere_les_places(): void
    {
        $experience = TourismExperience::factory()->published()->create(['capacity' => 8]);
        $this->seatBooking($experience, 5);
        $this->seatBooking($experience, 3, 'annulee_client'); // annulée → ne compte pas

        $this->assertSame(3, app(ExperienceBookingService::class)->seatsLeft($experience->fresh()));
    }

    /**
     * Crée une réservation occupant `$guests` places sur l'expérience.
     */
    private function seatBooking(TourismExperience $experience, int $guests, string $status = 'confirmee'): Booking
    {
        return Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => TourismExperience::class,
            'bookable_id' => $experience->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->addDays(2)->toDateString(),
            'guests' => $guests,
            'amount_xof' => $guests * 10_000,
            'status' => $status,
        ]);
    }
}
