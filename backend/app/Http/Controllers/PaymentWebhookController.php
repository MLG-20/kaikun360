<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\ApiResponse;
use App\Support\Payments\PaytechWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Réception des notifications PayTech (B14.3).
 *
 * SÉCURITÉ : la signature HMAC-SHA256 du corps brut est vérifiée AVANT toute
 * lecture métier ; une notification non authentifiée est rejetée (401) sans
 * aucun effet. La confirmation d'une réservation n'a lieu que sur un statut
 * COMPLETE vérifié ET un montant réconcilié — jamais sur une simple différence.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(private readonly PaytechWebhookVerifier $verifier)
    {
    }

    /**
     * POST /api/v1/payments/webhook  (public, signé)
     */
    public function handle(Request $request): JsonResponse
    {
        $raw = $request->getContent();

        // 1) Authenticité : refuser d'emblée toute notification non signée.
        if (! $this->verifier->verify($raw, $request->header('Signature'))) {
            Log::warning('Webhook PayTech rejeté : signature invalide.');

            return ApiResponse::error('Signature invalide.', 401);
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return ApiResponse::error('Charge utile invalide.', 400);
        }

        // 2) Retrouver la transaction locale.
        $providerRef = $payload['id'] ?? $payload['data']['id'] ?? null;
        $reference = $payload['reference'] ?? $payload['data']['reference'] ?? null;

        $payment = Payment::query()
            ->when($providerRef, fn ($q) => $q->orWhere('provider_reference', $providerRef))
            ->when($reference, fn ($q) => $q->orWhere('reference', $reference))
            ->first();

        if ($payment === null) {
            return ApiResponse::error('Paiement introuvable.', 404);
        }

        // Toute notification parvenue jusqu'ici est authentifiée.
        $payment->signature_verified = true;

        // 3) Idempotence : un paiement déjà encaissé n'est pas retraité.
        if ($payment->status === PaymentStatus::COMPLETE) {
            $payment->save();

            return ApiResponse::success(['status' => $payment->status->value]);
        }

        // 4) Mapper le statut PayTech ; un état inconnu est rejeté.
        $rawStatus = (string) ($payload['status'] ?? $payload['data']['status'] ?? '');
        $internal = PaymentStatus::fromPaytech($rawStatus);
        if ($internal === null) {
            $payment->save();

            return ApiResponse::error("Statut PayTech non reconnu : {$rawStatus}.", 422);
        }

        // 5) Réconciliation de montant : jamais de confirmation automatique si le
        //    montant débité diffère du montant attendu.
        $reportedAmount = $payload['amount'] ?? $payload['data']['amount'] ?? null;
        if ($internal === PaymentStatus::COMPLETE
            && $reportedAmount !== null
            && (int) $reportedAmount !== $payment->amount_xof) {
            $payment->meta = array_merge($payment->meta ?? [], [
                'amount_mismatch' => true,
                'reported_amount_xof' => (int) $reportedAmount,
            ]);
            $payment->save();

            Log::warning("Webhook PayTech : écart de montant sur {$payment->reference} (attendu {$payment->amount_xof}, reçu {$reportedAmount}).");

            return ApiResponse::success(['status' => $payment->status->value, 'reconciliation' => 'amount_mismatch'], status: 202);
        }

        // 6) Appliquer le statut ; confirmer la réservation si encaissé.
        $payment->status = $internal->value;
        if (isset($payload['mode']) || isset($payload['data']['mode'])) {
            $payment->mode = $payload['mode'] ?? $payload['data']['mode'];
        }
        $payment->save();

        if ($internal === PaymentStatus::COMPLETE) {
            // Audit d'une action sensible (validation de paiement, B15.3). Pas de
            // causer : la source est le PSP via un webhook authentifié.
            activity()->performedOn($payment)
                ->withProperties(['amount_xof' => $payment->amount_xof, 'commission_xof' => $payment->commission_xof])
                ->log('Validation de paiement');

            if ($payment->booking !== null && ! $payment->booking->status->estAnnulee()) {
                $payment->booking->update(['status' => BookingStatus::CONFIRMEE->value]);
            }
        }

        return ApiResponse::success(['status' => $payment->status->value]);
    }
}
