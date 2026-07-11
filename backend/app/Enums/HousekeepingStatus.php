<?php

namespace App\Enums;

/**
 * Statut de ménage d'une nuitée après le départ du client (B13.6).
 *
 * Piloté au back-office pour suivre la remise en état du logement entre deux
 * séjours.
 */
enum HousekeepingStatus: string
{
    case A_FAIRE = 'a_faire';
    case EN_COURS = 'en_cours';
    case FAIT = 'fait';

    public function label(): string
    {
        return match ($this) {
            self::A_FAIRE => 'À faire',
            self::EN_COURS => 'En cours',
            self::FAIT => 'Fait',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
