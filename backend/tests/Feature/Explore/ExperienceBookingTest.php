<?php

namespace Tests\Feature\Explore;

use App\Models\Booking;
use App\Models\User;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Explore\Models\TourismExperienceDeparture;
use App\Modules\Explore\Services\ExperienceBookingService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests de réservation et de capacité des expériences (phase B6.3, revue F21) :
 * panier groupe, places restantes PAR DATE DE DÉPART et refus de dépassement
 * de capacité.
 */
class ExperienceBookingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crée un circuit publié avec une seule date de départ, `$seats` places.
     */
    private function experienceWithDeparture(int $seats = 10, array $overrides = []): TourismExperienceDeparture
    {
        $experience = TourismExperience::factory()->published()->create($overrides);

        return $experience->departures()->create([
            'start_date' => now()->addWeek()->toDateString(),
            'seats_total' => $seats,
        ]);
    }

    public function test_les_places_restantes_tiennent_compte_des_groupes(): void
    {
        $departure = $this->experienceWithDeparture(10);
        $this->seatBooking($departure, 3);
        $this->seatBooking($departure, 2);

        $this->getJson("/api/v1/experiences/{$departure->tourism_experience_id}/availability")
            ->assertOk()
            ->assertJsonPath('data.departures.0.seats_left', 5);
    }

    public function test_un_client_reserve_des_places_en_groupe(): void
    {
        $departure = $this->experienceWithDeparture(10, ['price_xof' => 50_000, 'duration_days' => 3]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/experiences/{$departure->tourism_experience_id}/bookings", [
            'guests' => 4,
            'departure_id' => $departure->id,
        ])
            ->assertCreated()
            // 4 × 50 000 = 200 000.
            ->assertJsonPath('data.booking.amount_xof', 200_000)
            ->assertJsonPath('data.booking.guests', 4);

        $this->assertSame(6, app(ExperienceBookingService::class)->seatsLeft($departure->fresh()));
    }

    /**
     * F8.4 — la commission plateforme est FIGÉE à la réservation.
     *
     * Le tourisme ne l'enregistrait pas : la plateforme vendait des circuits
     * sans aucune trace de son revenu dans l'export comptable.
     */
    public function test_la_commission_plateforme_est_calculee_et_figee(): void
    {
        $departure = $this->experienceWithDeparture(10, ['price_xof' => 50_000, 'duration_days' => 3]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/experiences/{$departure->tourism_experience_id}/bookings", [
            'guests' => 4,
            'departure_id' => $departure->id,
        ])->assertCreated();

        // 12 % (taux de repli) de 200 000 = 24 000.
        $this->assertDatabaseHas('bookings', ['amount_xof' => 200_000, 'commission_xof' => 24_000]);
    }

    public function test_le_taux_de_commission_suit_le_reglage_du_back_office(): void
    {
        // Rien n'est codé en dur : la direction fixe le taux depuis les Paramètres.
        Settings::set('commission.default_rate', 8.0);

        $departure = $this->experienceWithDeparture(10, ['price_xof' => 50_000, 'duration_days' => 3]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/experiences/{$departure->tourism_experience_id}/bookings", [
            'guests' => 2,
            'departure_id' => $departure->id,
        ])->assertCreated();

        $this->assertDatabaseHas('bookings', ['commission_xof' => 8_000]); // 8 % de 100 000
    }

    public function test_la_reservation_refuse_le_depassement_de_capacite(): void
    {
        $departure = $this->experienceWithDeparture(5);
        $this->seatBooking($departure, 4);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/experiences/{$departure->tourism_experience_id}/bookings", [
            'guests' => 2, // 4 + 2 > 5
            'departure_id' => $departure->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('guests');
    }

    /**
     * F21 — une capacité pleine sur une date NE bloque PAS les autres dates du
     * même circuit : c'est tout le sens de la capacité par date de départ.
     */
    public function test_le_depassement_sur_une_date_n_affecte_pas_les_autres_dates(): void
    {
        $experience = TourismExperience::factory()->published()->create();
        $pleine = $experience->departures()->create(['start_date' => now()->addWeek()->toDateString(), 'seats_total' => 2]);
        $libre = $experience->departures()->create(['start_date' => now()->addWeeks(2)->toDateString(), 'seats_total' => 10]);
        $this->seatBooking($pleine, 2);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/experiences/{$experience->id}/bookings", [
            'guests' => 2,
            'departure_id' => $libre->id,
        ])->assertCreated();
    }

    public function test_la_reservation_exige_une_authentification(): void
    {
        $departure = $this->experienceWithDeparture();

        $this->postJson("/api/v1/experiences/{$departure->tourism_experience_id}/bookings", [
            'guests' => 1,
            'departure_id' => $departure->id,
        ])->assertStatus(401);
    }

    public function test_une_experience_non_publiee_n_est_pas_reservable(): void
    {
        $experience = TourismExperience::factory()->withDepartures(1)->create(); // en attente
        $departure = $experience->departures->first();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/experiences/{$experience->id}/bookings", [
            'guests' => 1,
            'departure_id' => $departure->id,
        ])->assertStatus(404);
    }

    public function test_une_date_de_depart_d_un_autre_circuit_est_refusee(): void
    {
        $departure = $this->experienceWithDeparture();
        $autre = $this->experienceWithDeparture();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/experiences/{$departure->tourism_experience_id}/bookings", [
            'guests' => 1,
            'departure_id' => $autre->id, // date d'un AUTRE circuit
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('departure_id');
    }

    public function test_une_annulation_libere_les_places(): void
    {
        $departure = $this->experienceWithDeparture(8);
        $this->seatBooking($departure, 5);
        $this->seatBooking($departure, 3, 'annulee_client'); // annulée → ne compte pas

        $this->assertSame(3, app(ExperienceBookingService::class)->seatsLeft($departure->fresh()));
    }

    /**
     * Crée une réservation occupant `$guests` places sur cette date de départ.
     */
    private function seatBooking(TourismExperienceDeparture $departure, int $guests, string $status = 'confirmee'): Booking
    {
        return Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => TourismExperience::class,
            'bookable_id' => $departure->tourism_experience_id,
            'start_date' => $departure->start_date,
            'end_date' => $departure->start_date->copy()->addDays(2),
            'guests' => $guests,
            'amount_xof' => $guests * 10_000,
            'status' => $status,
        ]);
    }
}
