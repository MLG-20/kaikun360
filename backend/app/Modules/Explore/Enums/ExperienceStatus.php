<?php

namespace App\Modules\Explore\Enums;

/**
 * Statuts du cycle de vie d'une expérience touristique (table `tourism_experiences`).
 *
 * Comme les biens immobiliers, une expérience n'est jamais visible publiquement
 * tant qu'un agent ne l'a pas validée (défaut EN_ATTENTE_VALIDATION).
 */
enum ExperienceStatus: string
{
    case EN_ATTENTE_VALIDATION = 'en_attente_validation';
    case PUBLIE = 'publie';
    case SUSPENDU = 'suspendu';
    case REJETE = 'rejete';

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE_VALIDATION => 'En attente de validation',
            self::PUBLIE => 'Publiée',
            self::SUSPENDU => 'Suspendue',
            self::REJETE => 'Rejetée',
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
