<?php

namespace Tests\Feature\Payment;

use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Modules\Stay\Models\Stay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests des ACOMPTES & SOLDES (F7.3.h — dernière ligne du module *Paiements*
 * du CDC §6).
 *
 * La table `payments` acceptait plusieurs règlements par réservation depuis B14,
 * mais rien ne les distinguait et aucun reste à payer n'était calculé : devant un
 * paiement de 50 000 F sur une réservation de 180 000 F, impossible de dire s'il
 * s'agissait d'un acompte ou d'une erreur.
 */
class PartialPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function booking(int $amount = 180_000): Booking
    {
        $stay = Stay::factory()->create();

        return Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => Stay::class,
            'bookable_id' => $stay->id,
            'start_date' => today()->addWeek(),
            'end_date' => today()->addWeek()->addDays(2),
            'guests' => 2,
            'amount_xof' => $amount,
            'commission_xof' => 21_600,
            'status' => BookingStatus::CONFIRMEE->value,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Le dernier cas passe par le back-office : les rôles doivent exister.
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    /** Encaisse un règlement (ce que fait le webhook PSP / la confirmation manuelle). */
    private function encaisser(Booking $booking, int $montant, PaymentKind $kind): Payment
    {
        return Payment::create([
            'reference' => 'PAY-'.uniqid(),
            'booking_id' => $booking->id,
            'provider' => 'paytech',
            'amount_xof' => $montant,
            'kind' => $kind->value,
            'status' => PaymentStatus::COMPLETE->value,
        ]);
    }

    public function test_un_versement_partiel_est_un_acompte(): void
    {
        $booking = $this->booking();
        Sanctum::actingAs($booking->user);

        $this->postJson('/api/v1/payments/initiate', [
            'booking_id' => $booking->id,
            'mode' => 'manuel',
            'amount_xof' => 50_000,
        ])->assertCreated()
            ->assertJsonPath('data.payment.kind', PaymentKind::ACOMPTE->value)
            ->assertJsonPath('data.payment.amount_xof', 50_000)
            ->assertJsonPath('data.instructions.remaining_after_xof', 130_000);
    }

    public function test_un_reglement_complet_d_emblee_est_integral(): void
    {
        $booking = $this->booking();
        Sanctum::actingAs($booking->user);

        // Montant omis = tout ce qui reste dû (comportement d'avant F7.3.h).
        $this->postJson('/api/v1/payments/initiate', [
            'booking_id' => $booking->id,
            'mode' => 'manuel',
        ])->assertCreated()
            ->assertJsonPath('data.payment.kind', PaymentKind::INTEGRAL->value)
            ->assertJsonPath('data.payment.amount_xof', 180_000);
    }

    public function test_le_second_versement_qui_solde_est_un_solde(): void
    {
        $booking = $this->booking();
        $this->encaisser($booking, 50_000, PaymentKind::ACOMPTE);

        Sanctum::actingAs($booking->user);

        $this->postJson('/api/v1/payments/initiate', [
            'booking_id' => $booking->id,
            'mode' => 'manuel',
        ])->assertCreated()
            ->assertJsonPath('data.payment.kind', PaymentKind::SOLDE->value)
            // Le solde porte sur ce qui reste, pas sur le montant total.
            ->assertJsonPath('data.payment.amount_xof', 130_000);
    }

    public function test_on_ne_peut_pas_payer_plus_que_le_reste_du(): void
    {
        $booking = $this->booking();
        $this->encaisser($booking, 150_000, PaymentKind::ACOMPTE);

        Sanctum::actingAs($booking->user);

        // Reste 30 000 : encaisser 50 000 créerait un trop-perçu à rembourser.
        $this->postJson('/api/v1/payments/initiate', [
            'booking_id' => $booking->id,
            'mode' => 'manuel',
            'amount_xof' => 50_000,
        ])->assertStatus(422);
    }

    public function test_un_acompte_n_empeche_pas_de_payer_le_solde(): void
    {
        $booking = $this->booking();
        $this->encaisser($booking, 50_000, PaymentKind::ACOMPTE);

        // Avant F7.3.h, un seul paiement encaissé rendait la réservation « payée »
        // et bloquait tout versement ultérieur.
        $this->assertFalse($booking->fresh()->estPayee());

        Sanctum::actingAs($booking->user);
        $this->postJson('/api/v1/payments/initiate', [
            'booking_id' => $booking->id,
            'mode' => 'manuel',
        ])->assertCreated();
    }

    public function test_une_reservation_soldee_n_est_plus_payable(): void
    {
        $booking = $this->booking();
        $this->encaisser($booking, 180_000, PaymentKind::INTEGRAL);

        $this->assertTrue($booking->fresh()->estPayee());
        $this->assertSame(0, $booking->fresh()->resteAPayer());

        Sanctum::actingAs($booking->user);
        $this->postJson('/api/v1/payments/initiate', [
            'booking_id' => $booking->id,
            'mode' => 'manuel',
        ])->assertStatus(422);
    }

    public function test_la_commission_n_est_prise_qu_une_fois_au_solde(): void
    {
        $booking = $this->booking();
        Sanctum::actingAs($booking->user);

        // L'acompte ne porte aucune commission…
        $acompte = $this->postJson('/api/v1/payments/initiate', [
            'booking_id' => $booking->id,
            'mode' => 'manuel',
            'amount_xof' => 50_000,
        ])->assertCreated()->json('data.payment');

        $this->assertSame(0, $acompte['commission_xof']);

        // …elle est prise sur le règlement qui solde, en une fois.
        $this->encaisser($booking, 50_000, PaymentKind::ACOMPTE);

        $solde = $this->postJson('/api/v1/payments/initiate', [
            'booking_id' => $booking->id,
            'mode' => 'manuel',
        ])->assertCreated()->json('data.payment');

        $this->assertSame(21_600, $solde['commission_xof']);
    }

    public function test_seuls_les_paiements_encaisses_comptent(): void
    {
        $booking = $this->booking();

        // Un règlement en attente de confirmation manuelle n'a rien apporté.
        Payment::create([
            'reference' => 'PAY-'.uniqid(),
            'booking_id' => $booking->id,
            'provider' => 'manuel',
            'amount_xof' => 90_000,
            'kind' => PaymentKind::ACOMPTE->value,
            'status' => PaymentStatus::EN_ATTENTE->value,
        ]);

        $this->assertSame(0, $booking->fresh()->montantPaye());
        $this->assertSame(180_000, $booking->fresh()->resteAPayer());
    }

    public function test_le_reste_a_payer_ne_devient_jamais_negatif(): void
    {
        $booking = $this->booking(100_000);
        $this->encaisser($booking, 120_000, PaymentKind::INTEGRAL);

        // Un trop-perçu se règle par un remboursement, pas par une dette négative.
        $this->assertSame(0, $booking->fresh()->resteAPayer());
    }

    public function test_le_back_office_voit_le_reste_a_payer(): void
    {
        $booking = $this->booking();
        $payment = $this->encaisser($booking, 50_000, PaymentKind::ACOMPTE);

        $admin = User::factory()->create();
        $admin->assignRole(\App\Modules\Core\Enums\UserRole::ADMIN->value);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/payments?booking_id='.$booking->id)
            ->assertOk()
            ->assertJsonPath('data.0.kind', PaymentKind::ACOMPTE->value)
            ->assertJsonPath('data.0.kind_label', 'Acompte')
            ->assertJsonPath('data.0.booking.paid_xof', 50_000)
            ->assertJsonPath('data.0.booking.remaining_xof', 130_000);

        $this->assertNotNull($payment->fresh());
    }
}
