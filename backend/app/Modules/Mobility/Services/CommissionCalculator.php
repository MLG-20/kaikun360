<?php

namespace App\Modules\Mobility\Services;

use App\Support\Settings;

/**
 * Calcul de la commission plateforme (phase B7.4 ; taux paramétrable en B13.4).
 *
 * Déclenché à chaque réservation de mobilité (véhicule ou service) pour figer la
 * commission Kaikun sur le montant. À défaut de taux explicite, on lit le taux
 * paramétré au back-office (`commission.default_rate`) ; la constante ci-dessous
 * sert d'ultime repli. Le règlement effectif relève du ledger de paiement (B14).
 */
class CommissionCalculator
{
    /**
     * Taux de commission de repli (pourcentage), si aucun réglage n'est défini.
     */
    public const DEFAULT_RATE = 12.0;

    /**
     * Commission (XOF) sur un montant donné, arrondie à l'entier.
     */
    public function commissionFor(int $amountXof, ?float $rate = null): int
    {
        $rate ??= (float) Settings::get('commission.default_rate', self::DEFAULT_RATE);

        return (int) round(max(0, $amountXof) * $rate / 100);
    }
}
