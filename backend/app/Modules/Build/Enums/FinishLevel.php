<?php

namespace App\Modules\Build\Enums;

/**
 * Niveau de finition souhaité (colonne `construction_requests.finish_level`).
 *
 * Sert de coefficient multiplicateur dans le simulateur de budget (B5.4).
 */
enum FinishLevel: string
{
    case ECONOMIQUE = 'economique';
    case STANDARD = 'standard';
    case PREMIUM = 'premium';

    public function label(): string
    {
        return match ($this) {
            self::ECONOMIQUE => 'Économique',
            self::STANDARD => 'Standard',
            self::PREMIUM => 'Premium',
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
