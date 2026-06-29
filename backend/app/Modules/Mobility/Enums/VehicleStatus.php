<?php

namespace App\Modules\Mobility\Enums;

/**
 * Statuts du cycle de vie d'un véhicule (table `vehicles`).
 *
 * Comme les biens et les expériences, un véhicule n'apparaît dans la recherche
 * qu'une fois validé (défaut EN_ATTENTE_VALIDATION).
 */
enum VehicleStatus: string
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
