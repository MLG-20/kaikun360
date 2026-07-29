<?php

namespace App\Modules\Build\Enums;

/**
 * Statut d'un devis de chantier (colonne `construction_quotes.status`) — F7.3.e2.
 *
 * Cycle : BROUILLON (composé au back-office) → ENVOYE (au client) → ACCEPTE /
 * REFUSE. Même cycle que les devis pack du team building (B9.2), dont ce module
 * reprend le motif.
 */
enum ConstructionQuoteStatus: string
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
