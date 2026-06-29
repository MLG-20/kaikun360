<?php

namespace App\Modules\Mobility\Enums;

/**
 * Statuts du cycle de vie d'un service de mobilité (table `mobility_services`).
 */
enum MobilityServiceStatus: string
{
    case EN_ATTENTE_VALIDATION = 'en_attente_validation';
    case PUBLIE = 'publie';
    case SUSPENDU = 'suspendu';
    case REJETE = 'rejete';

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE_VALIDATION => 'En attente de validation',
            self::PUBLIE => 'Publié',
            self::SUSPENDU => 'Suspendu',
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
