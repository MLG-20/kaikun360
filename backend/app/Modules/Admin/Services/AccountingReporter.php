<?php

namespace App\Modules\Admin\Services;

use App\Models\Booking;
use App\Modules\Manage\Enums\OwnerPayoutStatus;
use App\Modules\Manage\Models\OwnerPayout;
use Carbon\CarbonImmutable;

/**
 * Reporting comptable consolidé du back-office (B13.5).
 *
 * Rassemble sur une période les flux financiers de la plateforme :
 *   - réservations (volume encaissable + commission plateforme), les
 *     réservations annulées étant exclues des montants ;
 *   - reversements propriétaires effectués (module Manage).
 *
 * Produit une structure prête à l'export (JSON) ou à l'aplatissement (CSV).
 */
class AccountingReporter
{
    /**
     * Construit le rapport pour la période [from, to] (bornes incluses,
     * facultatives : null = pas de borne de ce côté).
     *
     * @return array<string, mixed>
     */
    public function report(?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        // Réservations sur la période (date de création).
        $bookings = Booking::query()
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->orderBy('created_at')
            ->get();

        // Les montants ne comptent que les réservations NON annulées.
        $active = $bookings->reject(fn (Booking $b) => $b->status->estAnnulee());

        // Reversements propriétaires effectués sur la période (date de paiement).
        $payouts = OwnerPayout::query()
            ->where('status', OwnerPayoutStatus::EFFECTUE->value)
            ->when($from, fn ($q) => $q->whereDate('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('paid_at', '<=', $to))
            ->orderBy('paid_at')
            ->get();

        return [
            'period' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
            'summary' => [
                'bookings_count' => $bookings->count(),
                'active_bookings_count' => $active->count(),
                'gross_volume_xof' => (int) $active->sum('amount_xof'),
                'commission_xof' => (int) $active->sum('commission_xof'),
                'payouts_count' => $payouts->count(),
                'payouts_total_xof' => (int) $payouts->sum('amount_xof'),
            ],
            'bookings' => $bookings->map(fn (Booking $b) => [
                'reference' => $b->reference,
                'date' => $b->created_at?->toDateString(),
                'type' => class_basename($b->bookable_type),
                'amount_xof' => $b->amount_xof,
                'commission_xof' => $b->commission_xof,
                'status' => $b->status->value,
            ])->values()->all(),
            'payouts' => $payouts->map(fn (OwnerPayout $p) => [
                'reference' => $p->reference,
                'paid_at' => $p->paid_at?->toDateString(),
                'owner_id' => $p->owner_id,
                'period_label' => $p->period_label,
                'amount_xof' => $p->amount_xof,
            ])->values()->all(),
        ];
    }
}
