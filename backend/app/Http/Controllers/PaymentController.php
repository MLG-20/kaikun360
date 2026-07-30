<?php

namespace App\Http\Controllers;

use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use App\Http\Requests\InitiatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Booking;
use App\Models\Payment;
use App\Support\ApiResponse;
use App\Support\Payments\PaymentProviderInterface;
use App\Support\Settings;
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
     * Deux modes (cahier des charges §11) :
     *  - `paytech` (défaut) : intention au PSP, renvoie l'URL de redirection ;
     *    la confirmation ne viendra QUE d'un webhook vérifié (B14.3).
     *  - `manuel` (Phase 1) : aucun appel PSP ; le client règle par Wave/Orange
     *    Money au numéro officiel et un admin confirmera dans le back-office.
     */
    public function initiate(InitiatePaymentRequest $request): JsonResponse
    {
        $booking = Booking::findOrFail($request->validated()['booking_id']);
        $mode = $request->validated()['mode'] ?? 'paytech';

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

        // F7.3.h — ACOMPTES & SOLDES. Montant omis = le client règle tout ce qui
        // reste dû (comportement d'avant cette tranche, préservé). Montant fourni
        // = versement partiel, plafonné au reste à payer : encaisser au-delà
        // créerait un trop-perçu à rembourser derrière.
        $reste = $booking->resteAPayer();
        $montant = (int) ($request->validated()['amount_xof'] ?? $reste);

        if ($montant > $reste) {
            throw ValidationException::withMessages([
                'amount_xof' => ["Il ne reste que {$reste} FCFA à régler sur cette réservation."],
            ]);
        }

        // La nature (acompte / solde / intégral) est DÉDUITE du montant : un
        // libellé saisi à la main finirait par mentir sur les chiffres.
        $kind = $booking->natureDuReglement($montant);

        // Transaction locale (montant et commission figés depuis la réservation).
        $payment = Payment::create([
            'reference' => 'PAY-'.Str::upper(Str::random(12)),
            'booking_id' => $booking->id,
            'provider' => $mode === 'manuel' ? 'manuel' : 'paytech',
            'amount_xof' => $montant,
            // La commission de la plateforme se prend UNE fois, sur le règlement
            // qui solde la réservation : la répartir sur chaque acompte donnerait
            // des arrondis qui ne retombent pas sur le total.
            'commission_xof' => $kind === PaymentKind::ACOMPTE ? 0 : ($booking->commission_xof ?? 0),
            'kind' => $kind->value,
            'status' => PaymentStatus::INITIE->value,
            'mode' => $mode,
        ]);

        // Paiement manuel (Phase 1) : on ne contacte pas le PSP. On renseigne le
        // client sur les modalités de règlement au numéro officiel.
        if ($mode === 'manuel') {
            $payment->update(['status' => PaymentStatus::EN_ATTENTE->value]);
            $payTo = (string) Settings::get('support.phone', '');

            return ApiResponse::created([
                'payment' => PaymentResource::make($payment->fresh()),
                'instructions' => [
                    'method' => 'Wave / Orange Money',
                    'pay_to' => $payTo,
                    'reference' => $payment->reference,
                    'message' => "Réglez {$payment->amount_xof} FCFA au numéro {$payTo} en indiquant la référence {$payment->reference}, puis confirmez par WhatsApp.",
                    'kind' => $payment->kind?->value,
                    'remaining_after_xof' => max(0, $reste - $montant),
                ],
            ]);
        }

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
