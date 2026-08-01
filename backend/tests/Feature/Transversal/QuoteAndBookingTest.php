<?php

namespace Tests\Feature\Transversal;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Stay\Models\Stay;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests transversaux B11.3 : devis (proposition/consultation/réponse) et
 * réservations (horodatage d'annulation automatique, /bookings/my).
 */
class QuoteAndBookingTest extends TestCase
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
        $agent->givePermissionTo(AdminPermission::operational()); // F7.1.b : agent pleinement outillé (droits désormais délégués, plus portés par le rôle)

        return $agent;
    }

    private function bookingFor(User $user, string $status = 'en_attente'): Booking
    {
        $vehicle = Vehicle::factory()->create();

        return Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => $user->id,
            'bookable_type' => Vehicle::class,
            'bookable_id' => $vehicle->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->addDays(2)->toDateString(),
            'guests' => 1,
            'amount_xof' => 100_000,
            'status' => $status,
        ]);
    }

    // --- Devis ----------------------------------------------------------------

    public function test_un_agent_propose_un_devis_mais_pas_un_client(): void
    {
        $request = ServiceRequest::factory()->create();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v1/requests/{$request->id}/quotes", ['amount_xof' => 500_000])
            ->assertStatus(403);

        Sanctum::actingAs($this->agent());
        $this->postJson("/api/v1/requests/{$request->id}/quotes", ['amount_xof' => 500_000])
            ->assertCreated()
            ->assertJsonPath('data.quote.status', 'envoye');
    }

    public function test_le_demandeur_consulte_son_devis_pas_un_tiers(): void
    {
        $owner = User::factory()->create();
        $request = ServiceRequest::factory()->create(['user_id' => $owner->id]);
        $quote = Quote::factory()->create(['request_id' => $request->id]);

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/quotes/{$quote->id}")->assertOk();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/quotes/{$quote->id}")->assertStatus(403);
    }

    public function test_le_demandeur_accepte_son_devis(): void
    {
        $owner = User::factory()->create();
        $request = ServiceRequest::factory()->create(['user_id' => $owner->id]);
        $quote = Quote::factory()->create(['request_id' => $request->id]); // envoye

        Sanctum::actingAs($owner);
        $this->patchJson("/api/v1/quotes/{$quote->id}", ['status' => 'accepte'])
            ->assertOk()
            ->assertJsonPath('data.quote.status', 'accepte');
    }

    public function test_un_tiers_ne_repond_pas_au_devis(): void
    {
        $quote = Quote::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/v1/quotes/{$quote->id}", ['status' => 'refuse'])->assertStatus(403);
    }

    public function test_on_ne_repond_pas_a_un_devis_deja_tranche(): void
    {
        $owner = User::factory()->create();
        $request = ServiceRequest::factory()->create(['user_id' => $owner->id]);
        $quote = Quote::factory()->create(['request_id' => $request->id, 'status' => 'accepte']);

        Sanctum::actingAs($owner);
        $this->patchJson("/api/v1/quotes/{$quote->id}", ['status' => 'refuse'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    // --- Réservations ---------------------------------------------------------

    public function test_l_annulation_fige_automatiquement_le_horodatage(): void
    {
        $booking = $this->bookingFor(User::factory()->create());
        $this->assertNull($booking->cancelled_at);

        $booking->update(['status' => 'annulee_client']);

        $this->assertNotNull($booking->fresh()->cancelled_at);
    }

    public function test_bookings_my_ne_liste_que_mes_reservations(): void
    {
        $user = User::factory()->create();
        $this->bookingFor($user);
        $this->bookingFor($user);
        $this->bookingFor(User::factory()->create()); // autre utilisateur

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/bookings/my')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /**
     * F8.6 — l'API dit enfin ce qu'il reste à payer.
     *
     * Elle ne le disait pas : aucun écran ne pouvait donc proposer de régler, et
     * un client pouvait réserver sans jamais avoir de moyen de payer.
     */
    public function test_l_etat_de_reglement_est_expose(): void
    {
        $user = User::factory()->create();
        $booking = $this->bookingFor($user); // 100 000 FCFA

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.booking.paid_xof', 0)
            ->assertJsonPath('data.booking.remaining_xof', 100_000)
            ->assertJsonPath('data.booking.is_paid', false)
            ->assertJsonPath('data.booking.payable', true);
    }

    /**
     * Seuls les paiements ENCAISSÉS comptent : un règlement Wave en attente de
     * confirmation n'a rien apporté et ne doit pas réduire le reste dû.
     */
    public function test_un_paiement_en_attente_ne_reduit_pas_le_reste_du(): void
    {
        $user = User::factory()->create();
        $booking = $this->bookingFor($user);

        Payment::create([
            'reference' => 'PAY-ATTENTE',
            'booking_id' => $booking->id,
            'amount_xof' => 40_000,
            'status' => PaymentStatus::EN_ATTENTE->value,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.booking.paid_xof', 0)
            ->assertJsonPath('data.booking.remaining_xof', 100_000);
    }

    public function test_un_acompte_encaisse_reduit_le_reste_du(): void
    {
        $user = User::factory()->create();
        $booking = $this->bookingFor($user);

        Payment::create([
            'reference' => 'PAY-ACOMPTE',
            'booking_id' => $booking->id,
            'amount_xof' => 40_000,
            'status' => PaymentStatus::COMPLETE->value,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.booking.paid_xof', 40_000)
            ->assertJsonPath('data.booking.remaining_xof', 60_000)
            ->assertJsonPath('data.booking.is_paid', false)
            ->assertJsonPath('data.booking.payable', true);
    }

    public function test_une_reservation_soldee_n_est_plus_payable(): void
    {
        $user = User::factory()->create();
        $booking = $this->bookingFor($user);

        Payment::create([
            'reference' => 'PAY-SOLDE',
            'booking_id' => $booking->id,
            'amount_xof' => 100_000,
            'status' => PaymentStatus::COMPLETE->value,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.booking.is_paid', true)
            ->assertJsonPath('data.booking.payable', false);
    }

    /** Une réservation annulée ne se règle pas, même si elle reste due. */
    public function test_une_reservation_annulee_n_est_pas_payable(): void
    {
        $user = User::factory()->create();
        $booking = $this->bookingFor($user);
        $booking->update(['status' => 'annulee_client']);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.booking.remaining_xof', 100_000)
            ->assertJsonPath('data.booking.payable', false);
    }

    public function test_bookings_my_expose_le_type_et_l_annulabilite(): void
    {
        $user = User::factory()->create();

        // Une location de véhicule (type annulable côté client).
        $vehicle = Vehicle::factory()->create(['brand' => 'Toyota', 'model' => 'Hilux']);
        Booking::create([
            'reference' => 'BK-VEH',
            'user_id' => $user->id,
            'bookable_type' => Vehicle::class,
            'bookable_id' => $vehicle->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->addDay()->toDateString(),
            'amount_xof' => 100_000,
            'status' => 'confirmee',
        ]);

        // Une nuitée (type SANS endpoint d'annulation client → non annulable).
        $stay = Stay::factory()->create();
        Booking::create([
            'reference' => 'BK-STAY',
            'user_id' => $user->id,
            'bookable_type' => Stay::class,
            'bookable_id' => $stay->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->addDays(2)->toDateString(),
            'amount_xof' => 150_000,
            'status' => 'confirmee',
        ]);

        Sanctum::actingAs($user);

        $data = $this->getJson('/api/v1/bookings/my')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->json('data');

        // Les réservations sont triées par date de création décroissante : la
        // nuitée (créée en second) arrive donc en premier.
        $stayRow = collect($data)->firstWhere('type', 'stay');
        $vehicleRow = collect($data)->firstWhere('type', 'vehicle');

        $this->assertSame('Nuitée', $stayRow['type_label']);
        $this->assertFalse($stayRow['cancellable']); // pas d'endpoint d'annulation nuitée

        $this->assertSame('Véhicule', $vehicleRow['type_label']);
        $this->assertSame('Toyota Hilux', $vehicleRow['item_label']);
        $this->assertTrue($vehicleRow['cancellable']); // véhicule confirmé → annulable
    }

    public function test_je_consulte_le_detail_de_ma_reservation(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['brand' => 'Toyota', 'model' => 'Hilux']);
        $booking = Booking::create([
            'reference' => 'BK-DETAIL',
            'user_id' => $user->id,
            'bookable_type' => Vehicle::class,
            'bookable_id' => $vehicle->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->addDay()->toDateString(),
            'amount_xof' => 100_000,
            'status' => 'confirmee',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.booking.id', $booking->id)
            ->assertJsonPath('data.booking.item_label', 'Toyota Hilux')
            ->assertJsonPath('data.booking.cancellable', true);
    }

    public function test_je_ne_peux_pas_consulter_la_reservation_d_un_autre(): void
    {
        $booking = $this->bookingFor(User::factory()->create()); // appartient à un autre

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/bookings/{$booking->id}")->assertStatus(403);
    }

    public function test_bookings_my_marque_non_annulable_une_reservation_deja_annulee(): void
    {
        $user = User::factory()->create();
        $this->bookingFor($user, 'annulee_client'); // véhicule, mais déjà annulé

        Sanctum::actingAs($user);

        $row = $this->getJson('/api/v1/bookings/my')->assertOk()->json('data.0');
        $this->assertSame('vehicle', $row['type']);
        $this->assertFalse($row['cancellable']); // déjà annulée → plus annulable
    }
}
