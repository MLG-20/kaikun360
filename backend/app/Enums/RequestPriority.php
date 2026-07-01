<?php

namespace App\Enums;

/**
 * Priorité d'une demande générique (colonne `requests.priority`).
 */
enum RequestPriority: string
{
    case NORMALE = 'normale';
    case HAUTE = 'haute';
    case URGENTE = 'urgente';

    public function label(): string
    {
        return match ($this) {
            self::NORMALE => 'Normale',
            self::HAUTE => 'Haute',
            self::URGENTE => 'Urgente',
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
