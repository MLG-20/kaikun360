<?php

namespace App\Modules\Pro\Enums;

/**
 * Catégorie de prestataire marketplace (colonne `providers.category`).
 */
enum ProviderCategory: string
{
    case RESTAURATION = 'restauration';
    case ANIMATION = 'animation';
    case GUIDE = 'guide';
    case TRANSPORT = 'transport';
    case EVENEMENTIEL = 'evenementiel';
    case ARTISANAT = 'artisanat';
    case AUTRE = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::RESTAURATION => 'Restauration',
            self::ANIMATION => 'Animation',
            self::GUIDE => 'Guide touristique',
            self::TRANSPORT => 'Transport',
            self::EVENEMENTIEL => 'Événementiel',
            self::ARTISANAT => 'Artisanat',
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
