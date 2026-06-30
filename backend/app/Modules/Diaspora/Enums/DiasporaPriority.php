<?php

namespace App\Modules\Diaspora\Enums;

/**
 * Priorité d'un dossier diaspora (colonne `diaspora_projects.priority`).
 *
 * Sert à la priorisation des dossiers à forte valeur côté back-office (B8.2).
 */
enum DiasporaPriority: string
{
    case NORMALE = 'normale';
    case HAUTE = 'haute';
    case STRATEGIQUE = 'strategique';

    public function label(): string
    {
        return match ($this) {
            self::NORMALE => 'Normale',
            self::HAUTE => 'Haute',
            self::STRATEGIQUE => 'Stratégique',
        };
    }

    /**
     * Poids de tri (plus élevé = plus prioritaire), pour le back-office.
     */
    public function weight(): int
    {
        return match ($this) {
            self::NORMALE => 0,
            self::HAUTE => 1,
            self::STRATEGIQUE => 2,
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
