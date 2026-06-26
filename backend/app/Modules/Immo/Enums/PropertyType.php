<?php

namespace App\Modules\Immo\Enums;

/**
 * Nature d'un bien immobilier (colonne `properties.type`).
 *
 * Couvre les principaux types du marché sénégalais. "terrain" est distingué
 * car il a des règles propres (pas de capacité, documents fonciers spécifiques).
 */
enum PropertyType: string
{
    case APPARTEMENT = 'appartement';
    case MAISON = 'maison';
    case VILLA = 'villa';
    case STUDIO = 'studio';
    case TERRAIN = 'terrain';
    case BUREAU = 'bureau';
    case COMMERCE = 'commerce';
    case AUTRE = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::APPARTEMENT => 'Appartement',
            self::MAISON => 'Maison',
            self::VILLA => 'Villa',
            self::STUDIO => 'Studio',
            self::TERRAIN => 'Terrain',
            self::BUREAU => 'Bureau',
            self::COMMERCE => 'Local commercial',
            self::AUTRE => 'Autre',
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
