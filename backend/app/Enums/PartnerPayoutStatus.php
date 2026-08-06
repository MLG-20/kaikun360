<?php

namespace App\Enums;

/**
 * Statut d'un versement à un partenaire (`partner_payouts.status`), F8.16.a.
 *
 * ⚠️ Trois états parce que **l'échec doit exister**. Un virement mobile money
 * peut être rejeté (numéro erroné, compte plafonné) : sans état d'échec, l'agent
 * n'aurait le choix qu'entre laisser le lot « payé » — donc mentir sur des
 * dettes qui restent dues — et le supprimer, ce qui effacerait la tentative.
 */
enum PartnerPayoutStatus: string
{
    /** Lot préparé, virement pas encore exécuté. */
    case EN_ATTENTE = 'en_attente';

    /** Virement exécuté et justifié. */
    case PAYE = 'paye';

    /**
     * Virement tenté et rejeté. Les dettes du lot **redeviennent exigibles** :
     * l'argent n'est pas parti, la créance du partenaire n'a pas disparu.
     */
    case ECHOUE = 'echoue';

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'À exécuter',
            self::PAYE => 'Payé',
            self::ECHOUE => 'Échoué',
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
