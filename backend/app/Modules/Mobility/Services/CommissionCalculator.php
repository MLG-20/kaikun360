<?php

namespace App\Modules\Mobility\Services;

/**
 * Calcul de la commission plateforme (phase B7.4).
 *
 * Déclenché à chaque réservation de mobilité (véhicule ou service) pour figer la
 * commission Kaikun sur le montant. Le taux par défaut s'applique à défaut de
 * taux spécifique (un taux par prestataire/catégorie pourra être branché plus
 * tard ; le règlement effectif relève du ledger de paiement, B14).
 */
class CommissionCalculator
{
    /**
     * Taux de commission par défaut (pourcentage).
     */
    public const DEFAULT_RATE = 12.0;

    /**
     * Commission (XOF) sur un montant donné, arrondie à l'entier.
     */
    public function commissionFor(int $amountXof, ?float $rate = null): int
    {
        $rate ??= self::DEFAULT_RATE;

        return (int) round(max(0, $amountXof) * $rate / 100);
    }
}
