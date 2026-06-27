<?php

namespace App\Modules\Manage\Enums;

/**
 * Statut de traitement d'un incident (colonne `incidents.status`).
 */
enum IncidentStatus: string
{
    case OUVERT = 'ouvert';
    case EN_COURS = 'en_cours';
    case RESOLU = 'resolu';
    case CLOS = 'clos';

    public function label(): string
    {
        return match ($this) {
            self::OUVERT => 'Ouvert',
            self::EN_COURS => 'En cours',
            self::RESOLU => 'Résolu',
            self::CLOS => 'Clos',
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
