<?php

namespace Tests\Feature\Payment;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Modules\Stay\Models\Stay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Tests B14.3 : webhook PayTech. La sécurité (signature HMAC-SHA256) est la
 * pierre angulaire : rien n'est traité sans authentification.
 */
class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNING_KEY = 'whsec_test_key';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.paytech.signing_key', self::SIGNING_KEY);
    }

    private function payment(array $overrides = []): Payment
    {
        $stay = Stay::factory()->create();
        $booking = Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => Stay::class,
            'bookable_id' => $stay->id,
            'start_date' => today(),
            'end_date' => today()->addDay(),
            'guests' => 1,
            'amount_xof' => 100_000,
            'commission_xof' => 12_000,
            'status' => 'en_attente',
        ]);

        return Payment::create(array_merge([
            'reference' => 'PAY-'.uniqid(),
            'booking_id' => $booking->id,
            'amount_xof' => 100_000,
            'commission_xof' => 12_000,
            'provider_reference' => 'ptx_'.uniqid(),
            'status' => PaymentStatus::EN_ATTENTE->value,
        ], $overrides));
    }

    private function sendWebhook(array $payload, ?string $signature = null): TestResponse
    {
        $raw = json_encode($payload);
        $signature ??= hash_hmac('sha256', $raw, self::SIGNING_KEY);

        return $this->call(
            'POST',
            '/api/v1/payments/webhook',
            [], [], [],
            ['HTTP_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            $raw,
        );
    }

    public function test_une_notification_sans_signature_valide_est_rejetee(): void
    {
        $payment = $this->payment();

        $this->sendWebhook(
            ['id' => $payment->provider_reference, 'status' => 'COMPLETED', 'amount' => 100_000],
            signature: 'mauvaise-signature',
        )->assertStatus(401);

        // Aucun effet : le paiement reste en attente, non vérifié.
        $this->assertSame(PaymentStatus::EN_ATTENTE, $payment->fresh()->status);
        $this->assertFalse($payment->fresh()->signature_verified);
    }

    public function test_un_paiement_complete_signe_confirme_la_reservation(): void
    {
        $payment = $this->payment();

        $this->sendWebhook(['id' => $payment->provider_reference, 'status' => 'COMPLETED', 'amount' => 100_000])
            ->assertOk()
            ->assertJsonPath('data.status', 'complete');

        $this->assertSame(PaymentStatus::COMPLETE, $payment->fresh()->status);
        $this->assertTrue($payment->fresh()->signature_verified);
        $this->assertSame('confirmee', $payment->fresh()->booking->status->value);
    }

    public function test_un_paiement_refuse_ne_confirme_pas_la_reservation(): void
    {
        $payment = $this->payment();

        $this->sendWebhook(['id' => $payment->provider_reference, 'status' => 'DECLINED'])
            ->assertOk()
            ->assertJsonPath('data.status', 'refuse');

        $this->assertSame('en_attente', $payment->fresh()->booking->status->value);
    }

    public function test_un_ecart_de_montant_ne_confirme_jamais_automatiquement(): void
    {
        $payment = $this->payment();

        // Montant débité différent de l'attendu.
        $this->sendWebhook(['id' => $payment->provider_reference, 'status' => 'COMPLETED', 'amount' => 90_000])
            ->assertStatus(202)
            ->assertJsonPath('data.reconciliation', 'amount_mismatch');

        // Ni complété, ni réservation confirmée.
        $this->assertNotSame(PaymentStatus::COMPLETE, $payment->fresh()->status);
        $this->assertSame('en_attente', $payment->fresh()->booking->status->value);
        $this->assertTrue($payment->fresh()->meta['amount_mismatch']);
    }

    public function test_un_statut_inconnu_est_rejete(): void
    {
        $payment = $this->payment();

        $this->sendWebhook(['id' => $payment->provider_reference, 'status' => 'WAT'])
            ->assertStatus(422);
    }

    public function test_un_paiement_deja_complete_est_idempotent(): void
    {
        $payment = $this->payment(['status' => PaymentStatus::COMPLETE->value]);

        $this->sendWebhook(['id' => $payment->provider_reference, 'status' => 'COMPLETED', 'amount' => 100_000])
            ->assertOk()
            ->assertJsonPath('data.status', 'complete');
    }

    public function test_un_paiement_introuvable_donne_404(): void
    {
        $this->sendWebhook(['id' => 'inconnu', 'status' => 'COMPLETED'])
            ->assertStatus(404);
    }
}
