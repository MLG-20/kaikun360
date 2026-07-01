<?php

namespace App\Modules\TeamBuilding\Enums;

/**
 * Statut d'un devis team building (colonne `team_building_quotes.status`).
 *
 * Cycle : BROUILLON (composé) → ENVOYE (à l'entreprise) → ACCEPTE / REFUSE.
 */
enum TeamBuildingQuoteStatus: string
{
    case BROUILLON = 'brouillon';
    case ENVOYE = 'envoye';
    case ACCEPTE = 'accepte';
    case REFUSE = 'refuse';

    public function label(): string
    {
        return match ($this) {
            self::BROUILLON => 'Brouillon',
            self::ENVOYE => 'Envoyé',
            self::ACCEPTE => 'Accepté',
            self::REFUSE => 'Refusé',
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
