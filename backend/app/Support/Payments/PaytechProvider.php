<?php

namespace App\Support\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Implémentation PayTech du contrat de paiement (B14, réécrite en F8.5).
 *
 * ⚠️ **Réécriture complète.** La version précédente s'adressait à une API qui
 * n'existe pas : base `engine-sandbox.pay.tech`, `POST /api/v1/payments`,
 * authentification `Bearer`, corps `amount`/`reference`/`callback_url`. Aucun de
 * ces éléments n'est celui de PayTech. Le premier appel réel aurait échoué. Ce
 * fichier suit désormais la documentation officielle (docs.intech.sn), point par
 * point :
 *
 * | Geste | Route |
 * |---|---|
 * | initier   | `POST /payment/request-payment` |
 * | consulter | `GET  /payment/get-status?token_payment=…` |
 * | rembourser| `POST /payment/refund-payment` |
 *
 * **Authentification** : deux en-têtes, `API_KEY` et `API_SECRET`. Il n'y a pas
 * de « signing key » séparée chez PayTech — c'est l'`API_SECRET` qui sert aussi
 * à signer les notifications ({@see PaytechWebhookVerifier}).
 *
 * **Vocabulaire PayTech**, à ne pas confondre avec le nôtre :
 *  - `ref_command` = NOTRE référence de paiement (`payments.reference`) ;
 *  - `token`       = LA référence de PayTech, stockée dans `provider_reference` ;
 *  - `item_price`  = le montant demandé ; `final_item_price` = le montant
 *    réellement débité (promotion appliquée, ou tirage aléatoire en test).
 *
 * ⚠️ **En environnement `test`, PayTech débite un montant ALÉATOIRE entre 100 et
 * 150 FCFA**, quel que soit le prix demandé. Toute réconciliation de montant doit
 * donc porter sur `item_price` (ce que nous avons demandé, que PayTech nous
 * renvoie tel quel) et jamais sur ce qui a été débité — sinon aucune réservation
 * ne serait jamais confirmée en sandbox.
 */
class PaytechProvider implements PaymentProviderInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly ?string $apiSecret,
        /** `test` (sandbox) ou `prod`. */
        private readonly string $env = 'test',
        private readonly ?string $ipnUrl = null,
        private readonly ?string $successUrl = null,
        private readonly ?string $cancelUrl = null,
    ) {
    }

    /**
     * Crée la demande de paiement et renvoie le jeton + l'URL de redirection.
     *
     * @param  array<string, mixed>  $context  Données libres renvoyées telles
     *                                         quelles dans l'IPN (`custom_field`).
     */
    public function initiate(Payment $payment, array $context = []): PaymentIntent
    {
        $this->assertConfigured();

        // `custom_field` revient en base64 dans l'IPN : on y met de quoi
        // retrouver le dossier même si la référence venait à manquer.
        $custom = json_encode([
            'payment_id' => $payment->id,
            'booking_id' => $payment->booking_id,
        ] + $context, JSON_THROW_ON_ERROR);

        $response = $this->client()->asForm()->post('/payment/request-payment', array_filter([
            'item_name' => $this->itemName($payment),
            'item_price' => $payment->amount_xof,
            'currency' => 'XOF',
            // NOTRE référence : c'est elle qui reviendra dans l'IPN et qui sert
            // à rembourser. Elle doit être unique côté PayTech.
            'ref_command' => $payment->reference,
            'command_name' => $this->itemName($payment),
            'env' => $this->env,
            'ipn_url' => $this->ipnUrl,
            // La référence voyage avec l'URL de retour : sans elle, le client
            // retombe sur une page qui ne sait pas de quel règlement elle parle.
            'success_url' => $this->returnUrl($this->successUrl, $payment),
            'cancel_url' => $this->returnUrl($this->cancelUrl, $payment),
            'custom_field' => $custom,
        ], static fn ($value) => $value !== null && $value !== ''));

        if (! $response->successful() || (int) $response->json('success') !== 1) {
            // On journalise la raison côté serveur sans jamais la renvoyer au
            // client : elle peut contenir des éléments de configuration.
            Log::error('PayTech : initiation refusée.', [
                'reference' => $payment->reference,
                'http' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new RuntimeException("Échec de l'initiation du paiement PayTech.");
        }

        $token = $response->json('token');
        $redirectUrl = $response->json('redirect_url') ?? $response->json('redirectUrl');

        if (empty($token) || empty($redirectUrl)) {
            throw new RuntimeException('Réponse PayTech incomplète (jeton ou URL manquante).');
        }

        return new PaymentIntent((string) $token, (string) $redirectUrl);
    }

    /**
     * PayTech n'a **pas** de geste de capture séparé : un paiement abouti l'est
     * déjà quand l'IPN arrive. « Confirmer » revient donc à réinterroger le PSP
     * — utile pour rattraper un IPN perdu, jamais pour forcer un encaissement.
     */
    public function confirm(Payment $payment): PaymentStatus
    {
        return $this->status($payment) ?? $payment->status;
    }

    /**
     * Rembourse un paiement. `POST /payment/refund-payment`, clé `ref_command`.
     *
     * ⚠️ **Le remboursement partiel n'est pas exposé par PayTech** : la route ne
     * prend que la référence de commande, pas de montant. `$amountXof` est donc
     * ignoré et un remboursement est toujours TOTAL. Le refuser ici plutôt que
     * de laisser croire à un remboursement partiel qui n'aura pas lieu.
     */
    public function refund(Payment $payment, ?int $amountXof = null): bool
    {
        $this->assertConfigured();

        if ($amountXof !== null && $amountXof !== $payment->amount_xof) {
            Log::warning('PayTech : remboursement partiel demandé, non supporté par le PSP.', [
                'reference' => $payment->reference,
                'demande' => $amountXof,
                'total' => $payment->amount_xof,
            ]);

            return false;
        }

        $response = $this->client()->asForm()->post('/payment/refund-payment', [
            'ref_command' => $payment->reference,
        ]);

        if (! $response->successful()) {
            Log::error('PayTech : remboursement refusé.', [
                'reference' => $payment->reference,
                'http' => $response->status(),
                'body' => $response->json(),
            ]);

            return false;
        }

        // PayTech répond `success: 1` sur une initiation de remboursement
        // acceptée ; l'aboutissement arrive plus tard par l'IPN
        // (`type_event = refund_complete`).
        return (int) $response->json('success') === 1;
    }

    /**
     * Statut courant d'après PayTech. `GET /payment/get-status?token_payment=…`
     *
     * Renvoie `null` si la transaction n'a pas encore de jeton (paiement jamais
     * initié) ou si PayTech ne répond pas : un statut indéterminé ne doit jamais
     * être confondu avec un refus.
     */
    public function status(Payment $payment): ?PaymentStatus
    {
        if (empty($payment->provider_reference)) {
            return null;
        }

        $this->assertConfigured();

        $response = $this->client()->get('/payment/get-status', [
            'token_payment' => $payment->provider_reference,
        ]);

        if (! $response->successful()) {
            return null;
        }

        // La forme exacte varie selon les versions : on accepte les deux clés
        // documentées plutôt que d'échouer sur un nom.
        $raw = (string) ($response->json('type_event')
            ?? $response->json('status')
            ?? $response->json('data.status')
            ?? '');

        return PaymentStatus::fromPaytech($raw);
    }

    /**
     * URL de retour porteuse de la référence du règlement.
     *
     * ⚠️ Cette référence sert seulement à AFFICHER le bon dossier au retour :
     * elle ne prouve rien. Un client qui ouvrirait l'URL de succès à la main ne
     * doit rien pouvoir déclencher — seul l'IPN signé confirme un encaissement.
     */
    private function returnUrl(?string $base, Payment $payment): ?string
    {
        if (empty($base)) {
            return null;
        }

        return $base.(str_contains($base, '?') ? '&' : '?').'ref='.urlencode($payment->reference);
    }

    /**
     * Libellé de la transaction, affiché au client sur la page de paiement.
     */
    private function itemName(Payment $payment): string
    {
        return 'Kaikun 360 — '.$payment->reference;
    }

    /**
     * Client HTTP authentifié. Les deux clés partent en EN-TÊTES (jamais dans
     * l'URL ni dans le corps, où elles finiraient dans les journaux d'accès).
     */
    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->withHeaders([
                'API_KEY' => (string) $this->apiKey,
                'API_SECRET' => (string) $this->apiSecret,
                'Accept' => 'application/json',
            ])
            ->timeout(20)
            // Une coupure réseau ne doit pas faire échouer un encaissement :
            // on retente deux fois, en laissant respirer entre deux essais.
            ->retry(2, 300, throw: false);
    }

    /**
     * Refuse de partir avec une configuration incomplète.
     *
     * Sans clés, PayTech répondrait une erreur générique qu'on présenterait au
     * client comme une panne du PSP — alors que c'est notre `.env` qui est vide.
     */
    private function assertConfigured(): void
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            throw new RuntimeException('PayTech non configuré : PAYTECH_API_KEY et PAYTECH_API_SECRET sont requis.');
        }
    }
}
