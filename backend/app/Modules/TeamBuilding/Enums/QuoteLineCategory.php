<?php

namespace App\Modules\TeamBuilding\Enums;

/**
 * Catégorie d'une ligne de devis team building (composition multi-prestataires).
 *
 * Chaque catégorie renvoie au module qui la fournit (le devis agrège plusieurs
 * modules : Stay/Manage, Explore, Mobility, + animation).
 */
enum QuoteLineCategory: string
{
    case LIEU = 'lieu';                 // Stay / Manage
    case HEBERGEMENT = 'hebergement';   // Stay
    case RESTAURATION = 'restauration'; // prestataire restauration
    case ACTIVITE = 'activite';         // Explore
    case MOBILITE = 'mobilite';         // Mobility
    case ANIMATION = 'animation';       // animation / prestataire

    public function label(): string
    {
        return match ($this) {
            self::LIEU => 'Lieu',
            self::HEBERGEMENT => 'Hébergement',
            self::RESTAURATION => 'Restauration',
            self::ACTIVITE => 'Activité',
            self::MOBILITE => 'Mobilité',
            self::ANIMATION => 'Animation',
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
