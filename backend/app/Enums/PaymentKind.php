<?php

namespace App\Enums;

/**
 * Nature d'un règlement (colonne `payments.kind`) — F7.3.h.
 *
 * Le CDC §6 *Paiements* attend « acomptes, soldes ». La table `payments` acceptait
 * depuis B14 plusieurs règlements pour une même réservation — sa migration cite
 * d'ailleurs « acompte, solde » — mais **rien ne les distinguait** et aucun reste à
 * payer n'était calculé.
 *
 * La nature est **déduite du montant réglé** au moment du paiement, jamais saisie :
 * un client qui verse une partie fait un acompte, celui qui solde fait un solde.
 * La déduire évite qu'un libellé mente sur les chiffres.
 */
enum PaymentKind: string
{
    case INTEGRAL = 'integral';
    case ACOMPTE = 'acompte';
    case SOLDE = 'solde';

    public function label(): string
    {
        return match ($this) {
            self::INTEGRAL => 'Paiement intégral',
            self::ACOMPTE => 'Acompte',
            self::SOLDE => 'Solde',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
