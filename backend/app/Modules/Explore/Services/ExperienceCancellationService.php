<?php

namespace App\Modules\Explore\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Support\Carbon;

/**
 * Logique d'annulation d'une réservation d'expérience (phase B6.4).
 *
 * Règle d'éligibilité au remboursement : l'annulation doit intervenir au moins
 * `REFUND_DELAY_DAYS` jours avant la date de départ. Le remboursement effectif
 * (via PayTech) est déclenché en phase B14 ; ici on calcule l'éligibilité et le
 * montant, on annule la réservation et on libère les places.
 */
class ExperienceCancellationService
{
    /**
     * Délai minimal (jours) avant le départ pour ouvrir droit à remboursement.
     */
    public const REFUND_DELAY_DAYS = 7;

    /**
     * La réservation est-elle éligible au remboursement (délai respecté) ?
     */
    public function isRefundEligible(Booking $booking): bool
    {
        $daysBefore = Carbon::today()->diffInDays(Carbon::parse($booking->start_date), false);

        return $daysBefore >= self::REFUND_DELAY_DAYS;
    }

    /**
     * Montant remboursable (montant total si éligible, sinon 0).
     */
    public function refundAmount(Booking $booking): int
    {
        return $this->isRefundEligible($booking) ? (int) $booking->amount_xof : 0;
    }

    /**
     * Annule la réservation (origine client) et renvoie l'info de remboursement.
     *
     * @return array{status: string, refund_eligible: bool, refund_amount_xof: int}
     */
    public function cancelByClient(Booking $booking): array
    {
        $eligible = $this->isRefundEligible($booking);
        $amount = $eligible ? (int) $booking->amount_xof : 0;

        $booking->update(['status' => BookingStatus::ANNULEE_CLIENT->value]);

        // 🔗 PayTech (B14) : si éligible, c'est ici que sera initié le remboursement.

        return [
            'status' => BookingStatus::ANNULEE_CLIENT->value,
            'refund_eligible' => $eligible,
            'refund_amount_xof' => $amount,
        ];
    }
}
