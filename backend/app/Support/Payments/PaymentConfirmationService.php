<?php

namespace App\Support\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\BookingConfirmedNotification;
use App\Support\Webhooks\WebhookDispatcher;

/**
 * Confirmation d'un paiement encaissé (B20).
 *
 * Source de vérité UNIQUE pour « un paiement passe à COMPLETE » : appelée à la
 * fois par le webhook PayTech vérifié (B14.3) et par la confirmation manuelle du
 * back-office (paiement Wave/Orange Money au numéro officiel, Phase 1 du cahier
 * des charges). Passe la réservation en `confirmée`, notifie le client, émet
 * l'événement n8n et journalise l'action sensible.
 */
class PaymentConfirmationService
{
    /**
     * Marque le paiement comme encaissé et applique tous ses effets métier.
     *
     * @param  Payment    $payment  transaction à confirmer (persistée)
     * @param  User|null  $actor    admin à l'origine (confirmation manuelle) ;
     *                              null pour une source automatique (webhook PSP)
     */
    public function markCompleted(Payment $payment, ?User $actor = null): void
    {
        $payment->status = PaymentStatus::COMPLETE->value;
        $payment->save();

        // Audit d'une action sensible (validation de paiement, B15.3). Le causer
        // n'existe que pour une confirmation humaine ; le webhook reste anonyme.
        $activity = activity()->performedOn($payment)
            ->withProperties(['amount_xof' => $payment->amount_xof, 'commission_xof' => $payment->commission_xof]);
        if ($actor !== null) {
            $activity->causedBy($actor);
        }
        $activity->log('Validation de paiement');

        $booking = $payment->booking;
        if ($booking === null || $booking->status->estAnnulee()) {
            return;
        }

        $booking->update(['status' => BookingStatus::CONFIRMEE->value]);

        // Confirme au client (async, e-mail + SMS) — B16.2.
        $booking->user?->notify(new BookingConfirmedNotification($booking));

        // Émet l'événement vers n8n (automatisation WhatsApp…) — B18.1.
        WebhookDispatcher::emit(WebhookDispatcher::BOOKING_CONFIRMED, [
            'booking_reference' => $booking->reference,
            'bookable_type' => class_basename((string) $booking->bookable_type),
            'amount_xof' => $payment->amount_xof,
            'user' => [
                'name' => $booking->user?->name,
                'phone' => $booking->user?->phone,
            ],
        ]);
    }
}
