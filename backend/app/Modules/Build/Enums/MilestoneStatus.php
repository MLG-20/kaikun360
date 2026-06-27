<?php

namespace App\Modules\Build\Enums;

/**
 * Statut d'un jalon de chantier (colonne `construction_milestones.status`).
 */
enum MilestoneStatus: string
{
    case A_VENIR = 'a_venir';
    case EN_COURS = 'en_cours';
    case TERMINE = 'termine';

    public function label(): string
    {
        return match ($this) {
            self::A_VENIR => 'À venir',
            self::EN_COURS => 'En cours',
            self::TERMINE => 'Terminé',
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
