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

    /**
     * F8.5 — les événements PayTech s'appellent `type_event` et valent
     * `sale_complete`, `sale_canceled`, `refund_complete`… Ce test vérifiait
     * auparavant `AUTHORIZED`/`COMPLETED`/`DECLINED`, qui ne sont PAS des
     * valeurs PayTech : il était vert contre un mapping qui n'aurait reconnu
     * aucune notification réelle.
     */
    public function test_le_mapping_des_evenements_paytech_est_correct(): void
    {
        $this->assertSame(PaymentStatus::COMPLETE, PaymentStatus::fromPaytech('sale_complete'));
        $this->assertSame(PaymentStatus::ANNULE, PaymentStatus::fromPaytech('sale_canceled'));
        $this->assertSame(PaymentStatus::REMBOURSE, PaymentStatus::fromPaytech('refund_complete'));
        $this->assertSame(PaymentStatus::COMPLETE, PaymentStatus::fromPaytech('transfer_success'));
        $this->assertSame(PaymentStatus::REFUSE, PaymentStatus::fromPaytech('transfer_failed'));
        // Casse et espaces parasites tolérés ; inconnu = null, jamais deviné.
        $this->assertSame(PaymentStatus::COMPLETE, PaymentStatus::fromPaytech('  SALE_COMPLETE '));
        $this->assertNull(PaymentStatus::fromPaytech('WAT'));
        $this->assertNull(PaymentStatus::fromPaytech('COMPLETED'));
    }

    public function test_le_conteneur_resout_paytech_comme_provider(): void
    {
        $this->assertInstanceOf(PaytechProvider::class, app(PaymentProviderInterface::class));
    }

    public function test_l_initiation_paytech_parse_la_reponse(): void
    {
        config()->set('services.paytech.base_url', 'https://paytech.sn/api');
        config()->set('services.paytech.api_key', 'test-key');
        config()->set('services.paytech.api_secret', 'test-secret');
        config()->set('services.paytech.env', 'test');
        config()->set('services.paytech.ipn_url', 'https://tunnel.test/api/v1/payments/webhook');

        Http::fake([
            'paytech.sn/*' => Http::response([
                'success' => 1,
                'token' => 'ptx_123',
                'redirect_url' => 'https://paytech.sn/payment/checkout/ptx_123',
            ], 200),
        ]);

        $payment = Payment::create([
            'reference' => 'PAY-1',
            'amount_xof' => 100_000,
            'status' => PaymentStatus::INITIE->value,
        ]);

        $intent = app(PaymentProviderInterface::class)->initiate($payment);

        $this->assertSame('ptx_123', $intent->providerReference);
        $this->assertSame('https://paytech.sn/payment/checkout/ptx_123', $intent->redirectUrl);

        // Le contrat PayTech, champ par champ : c'est ce que l'ancienne version
        // n'envoyait PAS (elle postait `amount`/`reference`/`callback_url`).
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/payment/request-payment')
                && $request['item_price'] === 100_000
                && $request['ref_command'] === 'PAY-1'
                && $request['currency'] === 'XOF'
                && $request['env'] === 'test'
                && $request['ipn_url'] === 'https://tunnel.test/api/v1/payments/webhook'
                // Les clés partent en EN-TÊTES, jamais dans le corps.
                && $request->hasHeader('API_KEY', 'test-key')
                && $request->hasHeader('API_SECRET', 'test-secret');
        });
    }

    /**
     * PayTech répond parfois HTTP 200 avec `success: 0` : traiter cela comme une
     * réussite renverrait le client vers une URL de paiement inexistante.
     */
    public function test_une_reponse_success_zero_leve_une_exception(): void
    {
        config()->set('services.paytech.base_url', 'https://paytech.sn/api');
        config()->set('services.paytech.api_key', 'test-key');
        config()->set('services.paytech.api_secret', 'test-secret');

        Http::fake(['paytech.sn/*' => Http::response(['success' => 0, 'message' => 'Clé invalide'], 200)]);

        $payment = Payment::create([
            'reference' => 'PAY-3',
            'amount_xof' => 50_000,
            'status' => PaymentStatus::INITIE->value,
        ]);

        $this->expectException(RuntimeException::class);
        app(PaymentProviderInterface::class)->initiate($payment);
    }

    /**
     * Sans clés, PayTech renverrait une erreur générique qu'on présenterait au
     * client comme une panne du PSP — alors que c'est notre `.env` qui est vide.
     */
    public function test_une_configuration_incomplete_leve_une_exception_explicite(): void
    {
        config()->set('services.paytech.api_key', null);
        config()->set('services.paytech.api_secret', null);

        $payment = Payment::create([
            'reference' => 'PAY-4',
            'amount_xof' => 50_000,
            'status' => PaymentStatus::INITIE->value,
        ]);

        $this->expectExceptionMessage('PayTech non configuré');
        app(PaymentProviderInterface::class)->initiate($payment);
    }

    public function test_une_initiation_en_echec_leve_une_exception(): void
    {
        config()->set('services.paytech.base_url', 'https://paytech.sn/api');
        config()->set('services.paytech.api_key', 'test-key');
        config()->set('services.paytech.api_secret', 'test-secret');

        Http::fake(['paytech.sn/*' => Http::response([], 500)]);

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
