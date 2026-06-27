<?php

namespace App\Modules\Build\Enums;

/**
 * Objectif d'une demande de construction (colonne `construction_requests.objective`).
 *
 * Détermine la nature des travaux et influe sur l'estimation du simulateur (B5.4).
 */
enum ConstructionObjective: string
{
    case CONSTRUCTION_NEUVE = 'construction_neuve';
    case RENOVATION = 'renovation';
    case EXTENSION = 'extension';

    public function label(): string
    {
        return match ($this) {
            self::CONSTRUCTION_NEUVE => 'Construction neuve',
            self::RENOVATION => 'Rénovation',
            self::EXTENSION => 'Extension',
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
