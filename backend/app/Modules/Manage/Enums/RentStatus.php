<?php

namespace App\Modules\Manage\Enums;

/**
 * Statut de paiement d'une échéance de loyer (colonne `rents.status`).
 */
enum RentStatus: string
{
    case IMPAYE = 'impaye';
    case PAYE = 'paye';
    case EN_RETARD = 'en_retard';

    public function label(): string
    {
        return match ($this) {
            self::IMPAYE => 'Impayé',
            self::PAYE => 'Payé',
            self::EN_RETARD => 'En retard',
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
