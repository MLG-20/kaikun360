<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\ApiResponse;
use App\Support\Payments\PaymentConfirmationService;
use App\Support\Payments\PaytechWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Réception des notifications de paiement PayTech — l'IPN (B14.3, réécrit F8.5).
 *
 * ⚠️ **Réécriture complète.** L'ancienne version lisait un JSON `{id, reference,
 * amount, status}` et une signature dans un en-tête `Signature`. PayTech ne
 * poste rien de tout cela : c'est un FORMULAIRE, la preuve d'authenticité est
 * dans le corps (`hmac_compute`), et le statut s'appelle `type_event`. Aucune
 * notification réelle n'aurait été acceptée.
 *
 * **C'est le seul chemin par lequel une réservation devient payée.** D'où
 * l'ordre, non négociable :
 *
 *   1. authenticité — signature vérifiée AVANT toute lecture métier ;
 *   2. identification de la transaction locale ;
 *   3. idempotence — PayTech réémet ses notifications, un encaissement déjà
 *      enregistré ne doit pas être rejoué ;
 *   4. traduction de l'événement — un événement inconnu est rejeté, jamais deviné ;
 *   5. réconciliation du montant ;
 *   6. application, et confirmation de la réservation si encaissé.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaytechWebhookVerifier $verifier,
        private readonly PaymentConfirmationService $confirmation,
    ) {
    }

    /**
     * POST /api/v1/payments/webhook  (public, authentifié par signature)
     */
    public function handle(Request $request): JsonResponse
    {
        // PayTech poste en `application/x-www-form-urlencoded` ; on accepte
        // aussi un corps JSON, certains environnements de test l'envoyant ainsi.
        $payload = $request->all();

        // 1) AUTHENTICITÉ. Rien n'est lu tant que ce n'est pas prouvé.
        if (! $this->verifier->verify($payload)) {
            Log::warning('IPN PayTech rejeté : signature invalide.', [
                'ref_command' => $payload['ref_command'] ?? null,
            ]);

            return ApiResponse::error('Signature invalide.', 401);
        }

        // 2) Retrouver la transaction. `ref_command` est NOTRE référence,
        //    `token` est celle de PayTech : on tente les deux, dans cet ordre.
        $reference = $payload['ref_command'] ?? null;
        $token = $payload['token'] ?? null;

        $payment = Payment::query()
            ->when($reference, fn ($q) => $q->where('reference', $reference))
            ->when(! $reference && $token, fn ($q) => $q->where('provider_reference', $token))
            ->first();

        if ($payment === null) {
            Log::warning('IPN PayTech : paiement introuvable.', ['ref_command' => $reference, 'token' => $token]);

            return ApiResponse::error('Paiement introuvable.', 404);
        }

        // Toute notification parvenue ici est authentifiée : c'est cette preuve
        // que le back-office affiche comme « encaissement prouvé » (F8.2).
        $payment->signature_verified = true;

        // Le jeton PayTech peut nous parvenir pour la première fois ici (IPN
        // plus rapide que la réponse d'initiation).
        if ($token && empty($payment->provider_reference)) {
            $payment->provider_reference = (string) $token;
        }

        // 3) IDEMPOTENCE. PayTech réémet ; un encaissement déjà acquis ne se
        //    rejoue pas — sinon on renotifie le client et on double les écritures.
        if ($payment->status === PaymentStatus::COMPLETE) {
            $payment->save();

            return ApiResponse::success(['status' => $payment->status->value, 'idempotent' => true]);
        }

        // 4) Traduire l'événement. Inconnu → rejet explicite, jamais d'inférence.
        $typeEvent = (string) ($payload['type_event'] ?? '');
        $internal = PaymentStatus::fromPaytech($typeEvent);

        if ($internal === null) {
            $payment->save();
            Log::warning("IPN PayTech : événement non reconnu « {$typeEvent} » sur {$payment->reference}.");

            return ApiResponse::error("Événement PayTech non reconnu : {$typeEvent}.", 422);
        }

        // 5) RÉCONCILIATION DU MONTANT.
        //    On compare à `item_price` — le montant que NOUS avons demandé et que
        //    PayTech nous renvoie — et surtout pas à `final_item_price` : en
        //    environnement `test`, PayTech débite un montant aléatoire entre 100
        //    et 150 FCFA, et en production une promotion peut l'abaisser
        //    légitimement. Les deux sont conservés pour la trace comptable.
        $requested = $payload['item_price'] ?? null;
        $debited = $payload['final_item_price'] ?? null;

        if ($internal === PaymentStatus::COMPLETE
            && $requested !== null
            && (int) $requested !== $payment->amount_xof) {
            $payment->meta = array_merge($payment->meta ?? [], [
                'amount_mismatch' => true,
                'reported_amount_xof' => (int) $requested,
                'debited_amount_xof' => $debited !== null ? (int) $debited : null,
            ]);
            $payment->save();

            Log::warning("IPN PayTech : écart de montant sur {$payment->reference} (attendu {$payment->amount_xof}, annoncé {$requested}).");

            // 202 : reçu et authentifié, mais NON appliqué — la réservation
            // reste impayée et un humain doit trancher depuis le back-office.
            return ApiResponse::success(
                ['status' => $payment->status->value, 'reconciliation' => 'amount_mismatch'],
                status: 202,
            );
        }

        // 6) Appliquer. On garde la trace de ce qui a réellement été débité et
        //    du moyen employé (Wave, Orange Money, carte…).
        $trace = array_filter([
            'debited_amount_xof' => $debited !== null ? (int) $debited : null,
            'payment_method' => $payload['payment_method'] ?? null,
            'client_phone' => $payload['client_phone'] ?? null,
            'env' => $payload['env'] ?? null,
        ], static fn ($v) => $v !== null);

        if ($trace !== []) {
            $payment->meta = array_merge($payment->meta ?? [], $trace);
        }

        if (! empty($payload['payment_method'])) {
            $payment->mode = (string) $payload['payment_method'];
        }

        if ($internal === PaymentStatus::COMPLETE) {
            // Source automatique (PSP) : pas de causer. Confirmation de la
            // réservation + notifications + événement n8n délégués au service
            // partagé (B20).
            $this->confirmation->markCompleted($payment);
        } else {
            $payment->status = $internal->value;
            $payment->save();
        }

        return ApiResponse::success(['status' => $payment->fresh()->status->value]);
    }
}
