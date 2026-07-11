<?php

namespace App\Enums;

/**
 * Statut d'un média (colonne `media.status`), couche transversale B12.
 *
 * Un média déposé est `actif` par défaut ; un modérateur peut le `masquer`
 * (contenu inapproprié) sans le supprimer, pour garder la trace.
 */
enum MediaStatus: string
{
    case ACTIF = 'actif';
    case MASQUE = 'masque';

    public function label(): string
    {
        return match ($this) {
            self::ACTIF => 'Actif',
            self::MASQUE => 'Masqué',
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
