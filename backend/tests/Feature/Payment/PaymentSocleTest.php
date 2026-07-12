<?php

namespace Tests\Feature\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\Payments\PaymentProviderInterface;
use App\Support\Payments\PaytechProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Tests B14.1 : socle de paiement (mapping des statuts, modèle Payment,
 * PaytechProvider via Http::fake, binding de l'interface).
 */
class PaymentSocleTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_mapping_des_statuts_paytech_est_correct(): void
    {
        $this->assertSame(PaymentStatus::AUTORISE, PaymentStatus::fromPaytech('AUTHORIZED'));
        $this->assertSame(PaymentStatus::COMPLETE, PaymentStatus::fromPaytech('completed'));
        $this->assertSame(PaymentStatus::REFUSE, PaymentStatus::fromPaytech('DECLINED'));
        $this->assertSame(PaymentStatus::ANNULE, PaymentStatus::fromPaytech('CANCELLED'));
        $this->assertSame(PaymentStatus::REMBOURSE, PaymentStatus::fromPaytech('REFUNDED'));
        $this->assertNull(PaymentStatus::fromPaytech('WAT'));
    }

    public function test_le_conteneur_resout_paytech_comme_provider(): void
    {
        $this->assertInstanceOf(PaytechProvider::class, app(PaymentProviderInterface::class));
    }

    public function test_l_initiation_paytech_parse_la_reponse(): void
    {
        config()->set('services.paytech.base_url', 'https://engine-sandbox.pay.tech');
        config()->set('services.paytech.api_key', 'test-key');

        Http::fake([
            'engine-sandbox.pay.tech/*' => Http::response([
                'id' => 'ptx_123',
                'redirect_url' => 'https://pay.tech/checkout/ptx_123',
            ], 200),
        ]);

        $payment = Payment::create([
            'reference' => 'PAY-1',
            'amount_xof' => 100_000,
            'status' => PaymentStatus::INITIE->value,
        ]);

        $intent = app(PaymentProviderInterface::class)->initiate($payment);

        $this->assertSame('ptx_123', $intent->providerReference);
        $this->assertSame('https://pay.tech/checkout/ptx_123', $intent->redirectUrl);

        // Le montant et la devise sont bien transmis à PayTech.
        Http::assertSent(fn ($request) => $request['amount'] === 100_000 && $request['currency'] === 'XOF');
    }

    public function test_une_initiation_en_echec_leve_une_exception(): void
    {
        config()->set('services.paytech.base_url', 'https://engine-sandbox.pay.tech');

        Http::fake(['engine-sandbox.pay.tech/*' => Http::response([], 500)]);

        $payment = Payment::create([
            'reference' => 'PAY-2',
            'amount_xof' => 50_000,
            'status' => PaymentStatus::INITIE->value,
        ]);

        $this->expectException(RuntimeException::class);
        app(PaymentProviderInterface::class)->initiate($payment);
    }

    public function test_le_statut_est_bien_caste_sur_le_modele(): void
    {
        $payment = Payment::create([
            'reference' => 'PAY-3',
            'amount_xof' => 20_000,
            'status' => PaymentStatus::COMPLETE->value,
        ]);

        $this->assertSame(PaymentStatus::COMPLETE, $payment->fresh()->status);
        $this->assertTrue($payment->fresh()->status->estReussi());
    }
}
