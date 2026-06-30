<?php

namespace App\Modules\Diaspora\Enums;

/**
 * Nature d'un projet diaspora (colonne `diaspora_projects.project_type`).
 *
 * Un membre de la diaspora pilote à distance un achat immobilier, une
 * construction ou la gestion locative d'un bien — accompagné par un agent dédié.
 */
enum DiasporaProjectType: string
{
    case ACHAT = 'achat';
    case CONSTRUCTION = 'construction';
    case GESTION_LOCATIVE = 'gestion_locative';

    public function label(): string
    {
        return match ($this) {
            self::ACHAT => 'Achat immobilier',
            self::CONSTRUCTION => 'Construction',
            self::GESTION_LOCATIVE => 'Gestion locative',
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
