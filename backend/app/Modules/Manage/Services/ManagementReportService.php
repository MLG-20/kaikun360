<?php

namespace App\Modules\Manage\Services;

use App\Modules\Manage\Enums\IncidentStatus;
use App\Modules\Manage\Enums\OwnerPayoutStatus;
use App\Modules\Manage\Enums\RentStatus;
use App\Modules\Manage\Models\Expense;
use App\Modules\Manage\Models\Incident;
use App\Modules\Manage\Models\ManagementMandate;
use App\Modules\Manage\Models\OwnerPayout;
use App\Modules\Manage\Models\Rent;
use Carbon\CarbonImmutable;

/**
 * Construit le rapport mensuel de gestion locative d'un mandat (phase B4.6).
 *
 * Le rapport est renvoyé sous forme de DONNÉES STRUCTURÉES (tableau) consommables
 * par le frontend ; l'export PDF est reporté à une phase ultérieure. Les périodes
 * sont bornées par mois calendaire (loyers par échéance `due_date`, dépenses par
 * `spent_at`, reversements effectués par `paid_at`).
 */
class ManagementReportService
{
    /**
     * @return array<string, mixed>
     */
    public function forMandate(ManagementMandate $mandate, CarbonImmutable $month): array
    {
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();

        // Loyers de l'échéance dans le mois.
        $rents = Rent::where('mandate_id', $mandate->id)
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()]);

        $rentsPaid = (int) (clone $rents)->where('status', RentStatus::PAYE->value)->sum('amount_xof');
        $rentsUnpaid = (int) (clone $rents)->whereIn('status', [
            RentStatus::IMPAYE->value,
            RentStatus::EN_RETARD->value,
        ])->sum('amount_xof');
        $rentsCount = (clone $rents)->count();

        // Dépenses du bien sur le mois.
        $expenses = Expense::where('property_id', $mandate->property_id)
            ->whereBetween('spent_at', [$start->toDateString(), $end->toDateString()]);
        $expensesTotal = (int) (clone $expenses)->sum('amount_xof');
        $expensesCount = (clone $expenses)->count();

        // Commission Kaikun sur les loyers encaissés, puis net dû au propriétaire.
        $commission = (int) round($rentsPaid * (float) $mandate->commission_rate / 100);
        $netOwner = $rentsPaid - $commission - $expensesTotal;

        // Reversements effectués sur le mois.
        $payouts = OwnerPayout::where('mandate_id', $mandate->id)
            ->where('status', OwnerPayoutStatus::EFFECTUE->value)
            ->whereBetween('paid_at', [$start, $end]);
        $payoutsTotal = (int) (clone $payouts)->sum('amount_xof');
        $payoutsCount = (clone $payouts)->count();

        // Incidents (ouverts sur le mois / résolus sur le mois).
        $incidentsOpened = Incident::where('property_id', $mandate->property_id)
            ->whereBetween('created_at', [$start, $end])->count();
        $incidentsResolved = Incident::where('property_id', $mandate->property_id)
            ->where('status', IncidentStatus::RESOLU->value)
            ->whereBetween('resolved_at', [$start, $end])->count();

        return [
            'mandate' => [
                'id' => $mandate->id,
                'reference' => $mandate->reference,
                'commission_rate' => $mandate->commission_rate,
            ],
            'period' => [
                'month' => $month->format('Y-m'),
                'label' => $month->locale('fr')->translatedFormat('F Y'),
            ],
            'rents' => [
                'paid_xof' => $rentsPaid,
                'unpaid_xof' => $rentsUnpaid,
                'count' => $rentsCount,
            ],
            'expenses' => [
                'total_xof' => $expensesTotal,
                'count' => $expensesCount,
            ],
            'commission_xof' => $commission,
            'net_owner_xof' => $netOwner,
            'payouts' => [
                'total_xof' => $payoutsTotal,
                'count' => $payoutsCount,
            ],
            'incidents' => [
                'opened' => $incidentsOpened,
                'resolved' => $incidentsResolved,
            ],
        ];
    }
}
