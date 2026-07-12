<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Http\Requests\InitiatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Booking;
use App\Models\Payment;
use App\Support\ApiResponse;
use App\Support\Payments\PaymentProviderInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Paiements (B14). Découplé du PSP : le contrôleur ne connaît que
 * PaymentProviderInterface, jamais PayTech directement.
 */
class PaymentController extends Controller
{
    public function __construct(private readonly PaymentProviderInterface $provider)
    {
    }

    /**
     * Initie le paiement d'une réservation. POST /api/v1/payments/initiate
     *
     * Crée une transaction locale, demande l'intention au PSP et renvoie l'URL
     * de redirection. La confirmation ne viendra QUE d'un webhook vérifié (B14.3).
     */
    public function initiate(InitiatePaymentRequest $request): JsonResponse
    {
        $booking = Booking::findOrFail($request->validated()['booking_id']);

        // Seul le titulaire de la réservation peut la régler.
        if ($booking->user_id !== $request->user()->id) {
            abort(403);
        }

        // Une réservation annulée ou déjà encaissée n'est pas payable.
        if ($booking->status->estAnnulee()) {
            throw ValidationException::withMessages(['booking_id' => ['Cette réservation est annulée.']]);
        }
        if ($booking->estPayee()) {
            throw ValidationException::withMessages(['booking_id' => ['Cette réservation est déjà payée.']]);
        }

        // Transaction locale (montant et commission figés depuis la réservation).
        $payment = Payment::create([
            'reference' => 'PAY-'.Str::upper(Str::random(12)),
            'booking_id' => $booking->id,
            'provider' => 'paytech',
            'amount_xof' => $booking->amount_xof,
            'commission_xof' => $booking->commission_xof ?? 0,
            'status' => PaymentStatus::INITIE->value,
        ]);

        try {
            $intent = $this->provider->initiate($payment);
        } catch (RuntimeException $e) {
            return ApiResponse::error('Le service de paiement est momentanément indisponible.', 502);
        }

        $payment->update([
            'status' => PaymentStatus::EN_ATTENTE->value,
            'provider_reference' => $intent->providerReference,
        ]);

        return ApiResponse::created([
            'payment' => PaymentResource::make($payment->fresh()),
            'redirect_url' => $intent->redirectUrl,
        ]);
    }
}
