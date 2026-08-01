<?php

namespace App\Support\Billing;

use App\Support\Settings;

/**
 * Calcul de la commission plateforme (B7.4 ; taux paramétrable depuis B13.4).
 *
 * **Source unique du taux, pour tous les univers.** À défaut de taux explicite,
 * on lit celui paramétré au back-office (`commission.default_rate`) ; la
 * constante ci-dessous n'est qu'un **ultime repli**, utilisé tant que la
 * direction n'a rien saisi — elle n'est pas « le » taux de Kaikun. Le règlement
 * effectif relève du ledger de paiement (B14).
 *
 * ⚠️ **La commission est FIGÉE à la réservation**, jamais recalculée ensuite :
 * un changement de taux au back-office ne doit pas réécrire l'historique
 * comptable des réservations déjà prises.
 *
 * Vivait dans `Modules/Mobility/Services/` (son premier appelant, B7.4). Déplacé
 * ici en F8.4 : sept modules s'en servent désormais — Mobilité, Nuitées,
 * Tourisme, Chantier, Team building, Pro — et un module métier n'a pas à
 * importer une règle transverse depuis un module voisin.
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
