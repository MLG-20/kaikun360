<?php

namespace App\Enums;

/**
 * Statut d'une dette envers un partenaire (`partner_dues.status`), F8.16.a.
 *
 * Quatre états, et pas un de plus. Chacun répond à une question que l'équipe se
 * pose vraiment devant l'écran « Reversements ».
 */
enum PartnerDueStatus: string
{
    /** Service rendu, mais le délai de sûreté court encore : à ne PAS payer. */
    case EN_ATTENTE = 'en_attente';

    /** Délai écoulé : payable dès qu'un agent prépare un lot. */
    case EXIGIBLE = 'exigible';

    /** Soldée par un versement (`payout_id` renseigné). */
    case PAYEE = 'payee';

    /**
     * Éteinte sans versement — réservation remboursée, mission annulée.
     *
     * ⚠️ **Annulée, jamais supprimée** : une dette effacée ne laisse aucune
     * trace de la raison, et deux agents rejouant le calcul la recréeraient. La
     * ligne reste, avec son motif (`cancelled_reason`).
     */
    case ANNULEE = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'En attente du délai',
            self::EXIGIBLE => 'Exigible',
            self::PAYEE => 'Payée',
            self::ANNULEE => 'Annulée',
        };
    }

    /** Cette dette peut-elle encore entrer dans un versement ? */
    public function estPayable(): bool
    {
        return $this === self::EXIGIBLE;
    }

    /** Cette dette est-elle encore vivante (ni payée ni annulée) ? */
    public function estVivante(): bool
    {
        return $this === self::EN_ATTENTE || $this === self::EXIGIBLE;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
