<?php

namespace App\Modules\Diaspora\Enums;

/**
 * Statut d'avancement d'un projet diaspora (colonne `diaspora_projects.status`).
 *
 * Cycle indicatif : NOUVEAU (déposé) → EN_COURS (agent affecté, travaux/suivi) →
 * TERMINE (ANNULE possible à tout moment).
 */
enum DiasporaProjectStatus: string
{
    case NOUVEAU = 'nouveau';
    case EN_COURS = 'en_cours';
    case TERMINE = 'termine';
    case ANNULE = 'annule';

    public function label(): string
    {
        return match ($this) {
            self::NOUVEAU => 'Nouveau',
            self::EN_COURS => 'En cours',
            self::TERMINE => 'Terminé',
            self::ANNULE => 'Annulé',
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
