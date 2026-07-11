<?php

namespace App\Enums;

/**
 * Statut de modération d'un avis (colonne `reviews.status`), couche transversale B12.
 *
 * Un avis déposé est `en_attente` de modération ; un modérateur le `publie`
 * (il compte alors dans la note agrégée) ou le `rejete`.
 */
enum ReviewStatus: string
{
    case EN_ATTENTE = 'en_attente';
    case PUBLIE = 'publie';
    case REJETE = 'rejete';

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'En attente',
            self::PUBLIE => 'Publié',
            self::REJETE => 'Rejeté',
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
