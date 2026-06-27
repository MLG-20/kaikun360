<?php

namespace App\Modules\Manage\Enums;

/**
 * Catégorie d'une dépense liée à un bien (colonne `expenses.category`).
 */
enum ExpenseCategory: string
{
    case MAINTENANCE = 'maintenance';
    case REPARATION = 'reparation';
    case AUTRE = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::MAINTENANCE => 'Maintenance',
            self::REPARATION => 'Réparation',
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
