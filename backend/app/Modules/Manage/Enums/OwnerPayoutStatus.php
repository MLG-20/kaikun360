<?php

namespace App\Modules\Manage\Enums;

/**
 * Statut d'un reversement au propriétaire (colonne `owner_payouts.status`).
 */
enum OwnerPayoutStatus: string
{
    case EN_ATTENTE = 'en_attente';
    case EFFECTUE = 'effectue';

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'En attente',
            self::EFFECTUE => 'Effectué',
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
