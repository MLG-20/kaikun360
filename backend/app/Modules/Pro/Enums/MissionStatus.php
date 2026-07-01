<?php

namespace App\Modules\Pro\Enums;

/**
 * Statut d'une mission affectée à un prestataire (colonne `provider_missions.status`).
 *
 * Cycle : AFFECTEE → ACCEPTEE → EN_COURS → TERMINEE (REFUSEE si le prestataire
 * décline ; ANNULEE côté plateforme).
 */
enum MissionStatus: string
{
    case AFFECTEE = 'affectee';
    case ACCEPTEE = 'acceptee';
    case EN_COURS = 'en_cours';
    case TERMINEE = 'terminee';
    case REFUSEE = 'refusee';
    case ANNULEE = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::AFFECTEE => 'Affectée',
            self::ACCEPTEE => 'Acceptée',
            self::EN_COURS => 'En cours',
            self::TERMINEE => 'Terminée',
            self::REFUSEE => 'Refusée',
            self::ANNULEE => 'Annulée',
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
