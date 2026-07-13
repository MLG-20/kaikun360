<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Support\ApiResponse;
use App\Support\Payments\PaymentConfirmationService;
use App\Support\Payments\PaymentProviderInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Supervision et remboursement des paiements par le back-office (B14.4).
 *
 * Réservé à la permission `gerer:paiements`. Le remboursement (caution à
 * restituer, annulation éligible côté Mobility / Explore / Stay) est délégué au
 * PSP via l'abstraction, jamais à PayTech directement.
 */
class AdminPaymentController extends Controller
{
    public function __construct(
        private readonly PaymentProviderInterface $provider,
        private readonly PaymentConfirmationService $confirmation,
    ) {
    }

    /**
     * Liste des paiements. GET /api/v1/admin/payments
     *
     * Filtres : `status`, `booking_id`, `reference` (référence interne ou PSP).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));

        $payments = Payment::query()
            ->with('booking')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('booking_id'), fn ($q) => $q->where('booking_id', $request->integer('booking_id')))
            ->when($request->filled('reference'), function ($q) use ($request) {
                $term = '%'.$request->string('reference')->toString().'%';
                $q->where(fn ($w) => $w->where('reference', 'like', $term)->orWhere('provider_reference', 'like', $term));
            })
            ->latest()
            ->paginate($perPage);

        return PaymentResource::collection($payments);
    }

    /**
     * Rembourse tout ou partie d'un paiement encaissé.
     * POST /api/v1/admin/payments/{payment}/refund
     */
    public function refund(Request $request, Payment $payment): JsonResponse
    {
        $data = $request->validate([
            'amount_xof' => ['nullable', 'integer', 'min:1'],
        ]);

        // Seul un paiement réellement encaissé est remboursable.
        if ($payment->status !== PaymentStatus::COMPLETE) {
            throw ValidationException::withMessages([
                'payment' => ['Seul un paiement encaissé peut être remboursé.'],
            ]);
        }

        $amount = $data['amount_xof'] ?? $payment->amount_xof;
        if ($amount > $payment->amount_xof) {
            throw ValidationException::withMessages([
                'amount_xof' => ['Le montant remboursé ne peut dépasser le montant payé.'],
            ]);
        }

        if (! $this->provider->refund($payment, $amount)) {
            return ApiResponse::error('Le remboursement a échoué côté PSP.', 502);
        }

        $payment->update([
            'status' => PaymentStatus::REMBOURSE->value,
            'meta' => array_merge($payment->meta ?? [], ['refunded_amount_xof' => $amount]),
        ]);

        activity()->causedBy($request->user())->performedOn($payment)
            ->withProperties(['amount_xof' => $amount])
            ->log('Remboursement de paiement');

        return ApiResponse::success(['payment' => PaymentResource::make($payment->fresh())]);
    }

    /**
     * Confirme manuellement un paiement encaissé hors PSP (Phase 1 du cahier des
     * charges). POST /api/v1/admin/payments/{payment}/confirm
     *
     * Cas d'usage : le client a réglé par Wave/Orange Money au numéro officiel ;
     * un admin valide la réception. La réservation est alors confirmée via le
     * service partagé, avec le causer admin (traçabilité, B15.3).
     */
    public function confirm(Request $request, Payment $payment): JsonResponse
    {
        $data = $request->validate([
            // Identifiant de la transaction Wave/OM, conservé comme preuve.
            'provider_reference' => ['nullable', 'string', 'max:255'],
        ]);

        // Réservé au flux manuel : un paiement PayTech se confirme par webhook.
        if ($payment->mode !== 'manuel') {
            throw ValidationException::withMessages([
                'payment' => ['Seul un paiement en mode manuel peut être confirmé à la main.'],
            ]);
        }
        if ($payment->status === PaymentStatus::COMPLETE) {
            throw ValidationException::withMessages([
                'payment' => ['Ce paiement est déjà confirmé.'],
            ]);
        }

        if (! empty($data['provider_reference'])) {
            $payment->provider_reference = $data['provider_reference'];
            $payment->meta = array_merge($payment->meta ?? [], [
                'manual_proof_reference' => $data['provider_reference'],
            ]);
        }

        $this->confirmation->markCompleted($payment, $request->user());

        return ApiResponse::success(['payment' => PaymentResource::make($payment->fresh())]);
    }
}
