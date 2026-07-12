<?php

namespace App\Support\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Implémentation PayTech du contrat de paiement (B14).
 *
 * Dialogue avec le moteur PayTech (`engine-sandbox.pay.tech` en test,
 * `engine.pay.tech` en prod) via l'API REST, authentifié par la clé API boutique
 * (en-tête Bearer). Toutes les valeurs sensibles viennent de la configuration
 * (`config/services.php` → `paytech`), jamais du code.
 *
 * Les chemins/DTO exacts suivent la documentation PayTech et sont validés en
 * sandbox ; le parsing des réponses est défensif pour tolérer les variantes.
 */
class PaytechProvider implements PaymentProviderInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly ?string $webhookUrl,
    ) {
    }

    public function initiate(Payment $payment, array $context = []): PaymentIntent
    {
        $response = $this->client()->post('/api/v1/payments', [
            'amount' => $payment->amount_xof,
            'currency' => 'XOF',
            'reference' => $payment->reference,
            'callback_url' => $this->webhookUrl,
            'metadata' => ['booking_id' => $payment->booking_id] + $context,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Échec de l\'initiation du paiement PayTech.');
        }

        $providerRef = $response->json('id') ?? $response->json('data.id');
        $redirectUrl = $response->json('redirect_url')
            ?? $response->json('checkout_url')
            ?? $response->json('data.redirect_url');

        if ($providerRef === null || $redirectUrl === null) {
            throw new RuntimeException('Réponse PayTech incomplète (référence ou URL manquante).');
        }

        return new PaymentIntent((string) $providerRef, (string) $redirectUrl);
    }

    public function confirm(Payment $payment): PaymentStatus
    {
        $response = $this->client()->post("/api/v1/payments/{$payment->provider_reference}/confirm");

        return PaymentStatus::fromPaytech((string) $response->json('status', '')) ?? $payment->status;
    }

    public function refund(Payment $payment, ?int $amountXof = null): bool
    {
        $response = $this->client()->post("/api/v1/payments/{$payment->provider_reference}/refund", [
            'amount' => $amountXof ?? $payment->amount_xof,
        ]);

        return $response->successful();
    }

    public function status(Payment $payment): ?PaymentStatus
    {
        $response = $this->client()->get("/api/v1/payments/{$payment->provider_reference}");

        if (! $response->successful()) {
            return null;
        }

        return PaymentStatus::fromPaytech((string) $response->json('status', ''));
    }

    /**
     * Client HTTP préconfiguré (base URL + authentification Bearer).
     */
    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken((string) $this->apiKey)
            ->acceptJson()
            ->timeout(15);
    }
}
