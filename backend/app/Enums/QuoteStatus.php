<?php

namespace App\Enums;

/**
 * Statut d'un devis générique (colonne `quotes.status`), couche transversale B11.
 *
 * Cycle : BROUILLON (préparé) → ENVOYE (au client) → ACCEPTE / REFUSE.
 */
enum QuoteStatus: string
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
