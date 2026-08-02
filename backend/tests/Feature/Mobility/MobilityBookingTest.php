<?php

namespace Tests\Feature\Mobility;

use App\Enums\CautionStatus;
use App\Models\Booking;
use App\Models\User;
use App\Modules\Mobility\Models\MobilityService;
use App\Modules\Mobility\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests des réservations de mobilité (phase B7.4) : caution & commission sur les
 * locations de véhicule, capacité & commission sur les services de mobilité.
 */
class MobilityBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_location_calcule_montant_commission_et_retient_la_caution(): void
    {
        $vehicle = Vehicle::factory()->published()->create([
            'price_per_day_xof' => 50_000,
            'caution_xof' => 100_000,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/vehicles/{$vehicle->id}/bookings", [
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
        ])
            ->assertCreated()
            // 3 jours × 50 000 = 150 000.
            ->assertJsonPath('data.booking.amount_xof', 150_000)
            // 12 % de 150 000 = 18 000.
            ->assertJsonPath('data.booking.commission_xof', 18_000)
            ->assertJsonPath('data.booking.caution_xof', 100_000)
            ->assertJsonPath('data.booking.caution_status', 'retenue');
    }

    /**
     * F8.10 — anti double-location. Le contrôle MANQUAIT : deux clients
     * pouvaient louer le même véhicule aux mêmes dates. Invisible tant qu'aucun
     * écran ne créait de réservation ; le catalogue en produit désormais.
     */
    /**
     * F8.10 — fiche publique d'un départ. L'endpoint n'existait pas : le module
     * n'exposait que la recherche, un trajet ne pouvait donc pas avoir de page
     * et sa réservation passait forcément par un conseiller.
     */
    public function test_la_fiche_d_un_depart_donne_son_remplissage(): void
    {
        $service = MobilityService::factory()->published()->create([
            'capacity' => 30,
            'price_xof' => 5_000,
        ]);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v1/mobility-services/{$service->id}/bookings", ['guests' => 4])
            ->assertCreated();

        // Public : on consulte un départ sans être connecté.
        app('auth')->forgetGuards();

        $this->getJson("/api/v1/mobility-services/{$service->id}")
            ->assertOk()
            ->assertJsonPath('data.mobility_service.id', $service->id)
            ->assertJsonPath('data.seats_taken', 4)
            ->assertJsonPath('data.seats_left', 26);
    }

    /** Un départ non publié n'a pas de fiche publique. */
    public function test_un_depart_non_publie_est_introuvable(): void
    {
        // Le statut par défaut de la factory est « en attente de validation ».
        $service = MobilityService::factory()->create();

        $this->getJson("/api/v1/mobility-services/{$service->id}")->assertNotFound();
    }

    public function test_un_vehicule_deja_loue_sur_la_periode_est_refuse(): void
    {
        $vehicle = Vehicle::factory()->published()->create(['price_per_day_xof' => 50_000]);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v1/vehicles/{$vehicle->id}/bookings", [
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
        ])->assertCreated();

        // Un autre client, une période qui empiète : refus.
        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v1/vehicles/{$vehicle->id}/bookings", [
            'start_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.start_date.0', 'Ce véhicule est déjà loué sur cette période.');

        // Une période disjointe reste possible : on n'a pas bloqué le véhicule.
        $this->postJson("/api/v1/vehicles/{$vehicle->id}/bookings", [
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(22)->toDateString(),
        ])->assertCreated();
    }

    /**
     * Une location ANNULÉE ne doit plus bloquer le véhicule : sinon une
     * annulation retirerait le véhicule du marché pour rien.
     */
    public function test_une_location_annulee_libere_la_periode(): void
    {
        $vehicle = Vehicle::factory()->published()->create(['price_per_day_xof' => 50_000]);

        $client = User::factory()->create();
        Sanctum::actingAs($client);
        $reponse = $this->postJson("/api/v1/vehicles/{$vehicle->id}/bookings", [
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
        ])->assertCreated();

        $this->patchJson("/api/v1/vehicles/bookings/{$reponse->json('data.booking.id')}/cancel")
            ->assertOk();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v1/vehicles/{$vehicle->id}/bookings", [
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
        ])->assertCreated();
    }

    public function test_annulation_conforme_restitue_la_caution_et_ouvre_le_remboursement(): void
    {
        $client = User::factory()->create();
        $booking = $this->vehicleBooking($client, daysAhead: 5);

        Sanctum::actingAs($client);

        $this->patchJson("/api/v1/vehicles/bookings/{$booking->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.booking.caution_status', 'restituee')
            ->assertJsonPath('data.refund.eligible', true)
            ->assertJsonPath('data.refund.amount_xof', 150_000);
    }

    public function test_annulation_tardive_fait_perdre_la_caution(): void
    {
        $client = User::factory()->create();
        $booking = $this->vehicleBooking($client, daysAhead: 1); // < 2 jours

        Sanctum::actingAs($client);

        $this->patchJson("/api/v1/vehicles/bookings/{$booking->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.booking.caution_status', 'perdue')
            ->assertJsonPath('data.refund.eligible', false)
            ->assertJsonPath('data.refund.amount_xof', 0);
    }

    public function test_un_tiers_ne_peut_pas_annuler_la_location(): void
    {
        $client = User::factory()->create();
        $booking = $this->vehicleBooking($client, daysAhead: 5);

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/v1/vehicles/bookings/{$booking->id}/cancel")->assertStatus(403);
    }

    public function test_le_service_de_mobilite_fige_la_commission(): void
    {
        $service = MobilityService::factory()->published()->create([
            'capacity' => 10, 'price_xof' => 20_000,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/mobility-services/{$service->id}/bookings", ['guests' => 3])
            ->assertCreated()
            // 3 × 20 000 = 60 000 ; commission 12 % = 7 200.
            ->assertJsonPath('data.booking.amount_xof', 60_000)
            ->assertJsonPath('data.booking.commission_xof', 7_200);
    }

    public function test_le_service_refuse_le_depassement_de_capacite(): void
    {
        $service = MobilityService::factory()->published()->create(['capacity' => 4, 'price_xof' => 10_000]);
        Booking::create([
            'reference' => 'BK-MOB-1',
            'user_id' => User::factory()->create()->id,
            'bookable_type' => MobilityService::class,
            'bookable_id' => $service->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'guests' => 3,
            'amount_xof' => 30_000,
            'status' => 'confirmee',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/mobility-services/{$service->id}/bookings", ['guests' => 2])
            ->assertStatus(422)
            ->assertJsonValidationErrors('guests');
    }

    /**
     * Crée une location de véhicule (caution retenue) avec départ dans `$daysAhead` jours.
     */
    private function vehicleBooking(User $user, int $daysAhead): Booking
    {
        $vehicle = Vehicle::factory()->published()->create(['caution_xof' => 80_000]);

        return Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => $user->id,
            'bookable_type' => Vehicle::class,
            'bookable_id' => $vehicle->id,
            'start_date' => now()->addDays($daysAhead)->toDateString(),
            'end_date' => now()->addDays($daysAhead + 3)->toDateString(),
            'guests' => 1,
            'amount_xof' => 150_000,
            'caution_xof' => 80_000,
            'caution_status' => CautionStatus::RETENUE->value,
            'status' => 'confirmee',
        ]);
    }
}
