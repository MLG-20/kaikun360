<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Mobility\Models\MobilityService;
use App\Modules\Mobility\Models\Vehicle;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests F8.2.b : **fiches de l'écran Mobilité** — un véhicule, un départ.
 *
 * La liste signale qu'un véhicule n'est pas conforme ; la fiche dit ce qu'il
 * engage (locations, départs programmés à venir) et qui appeler. La liste donne
 * le remplissage d'un départ (« 12 / 15 ») ; la fiche donne **qui** sont ces
 * douze — c'est avec cette liste en main qu'un départ se prépare.
 */
class MobilityDossierTest extends TestCase
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

    /** Réservation sur une ressource polymorphe (véhicule ou trajet). */
    private function booking(string $type, int $id, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'reference' => 'BK-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => $type,
            'bookable_id' => $id,
            'start_date' => today(),
            'end_date' => today()->addDay(),
            'guests' => 2,
            'amount_xof' => 40_000,
            'status' => 'confirmee',
        ], $overrides));
    }

    // --- Véhicule ---------------------------------------------------------------

    public function test_un_utilisateur_sans_acces_back_office_est_refuse(): void
    {
        $vehicle = Vehicle::factory()->create();
        $trip = MobilityService::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/admin/vehicles/{$vehicle->id}")->assertStatus(403);
        $this->getJson("/api/v1/admin/mobility-services/{$trip->id}")->assertStatus(403);
    }

    public function test_la_fiche_vehicule_montre_conformite_locations_et_departs(): void
    {
        $vehicle = Vehicle::factory()->create([
            'insurance_ref' => 'ASS-2026-118',
            'has_driver' => true,
            'driver_identity' => 'Moussa Fall',
        ]);

        $this->booking(Vehicle::class, $vehicle->id);

        // Un départ à venir porté par ce véhicule : c'est ce qu'une suspension
        // mettrait par terre.
        $trip = MobilityService::factory()->create([
            'vehicle_id' => $vehicle->id,
            'capacity' => 15,
            'departure_at' => now()->addWeek(),
        ]);
        $this->booking(MobilityService::class, $trip->id, ['guests' => 4]);

        Sanctum::actingAs($this->agent());

        $this->getJson("/api/v1/admin/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('data.vehicle.id', $vehicle->id)
            // Les champs de contrôle, que le catalogue public n'expose pas.
            ->assertJsonPath('data.vehicle.insurance_ref', 'ASS-2026-118')
            ->assertJsonPath('data.vehicle.driver_identity', 'Moussa Fall')
            ->assertJsonPath('data.vehicle.provider.id', $vehicle->provider_id)
            ->assertJsonCount(1, 'data.bookings')
            ->assertJsonCount(1, 'data.trips')
            ->assertJsonPath('data.trips.0.seats_taken', 4)
            ->assertJsonPath('data.trips.0.seats_left', 11)
            ->assertJsonPath('data.trips.0.is_upcoming', true);
    }

    // --- Trajet -----------------------------------------------------------------

    public function test_la_fiche_trajet_liste_ses_passagers(): void
    {
        $trip = MobilityService::factory()->create(['capacity' => 15]);

        $client = User::factory()->create(['name' => 'Fatou Sarr']);
        $this->booking(MobilityService::class, $trip->id, [
            'user_id' => $client->id,
            'guests' => 3,
            'amount_xof' => 60_000,
        ]);

        Sanctum::actingAs($this->agent());

        $this->getJson("/api/v1/admin/mobility-services/{$trip->id}")
            ->assertOk()
            ->assertJsonPath('data.trip.id', $trip->id)
            ->assertJsonPath('data.trip.seats_taken', 3)
            ->assertJsonPath('data.trip.seats_left', 12)
            ->assertJsonCount(1, 'data.passengers')
            ->assertJsonPath('data.passengers.0.client_name', 'Fatou Sarr')
            ->assertJsonPath('data.passengers.0.guests', 3)
            // Rien n'a été encaissé : le solde dû doit se voir avant le départ.
            ->assertJsonPath('data.passengers.0.remaining_xof', 60_000);
    }

    public function test_une_reservation_annulee_reste_listee_mais_ne_compte_pas(): void
    {
        $trip = MobilityService::factory()->create(['capacity' => 15]);

        $this->booking(MobilityService::class, $trip->id, ['guests' => 2]);
        $this->booking(MobilityService::class, $trip->id, [
            'guests' => 5,
            'status' => 'annulee_client',
        ]);

        Sanctum::actingAs($this->agent());

        $this->getJson("/api/v1/admin/mobility-services/{$trip->id}")
            ->assertOk()
            // Seules les 2 places non annulées sont décomptées…
            ->assertJsonPath('data.trip.seats_taken', 2)
            ->assertJsonPath('data.trip.seats_left', 13)
            // …mais l'annulation reste visible : elle explique un départ vide.
            ->assertJsonCount(2, 'data.passengers')
            ->assertJsonPath(
                'data.passengers.*.is_cancelled',
                fn (array $flags) => in_array(true, $flags, true) && in_array(false, $flags, true),
            );
    }
}
