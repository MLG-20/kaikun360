<?php

namespace App\Modules\Manage\Enums;

/**
 * Statut d'un mandat de gestion locative (colonne `management_mandates.status`).
 *
 * Cycle : EN_ATTENTE (signature) → ACTIF → (SUSPENDU) → TERMINE.
 */
enum MandateStatus: string
{
    case EN_ATTENTE = 'en_attente';
    case ACTIF = 'actif';
    case SUSPENDU = 'suspendu';
    case TERMINE = 'termine';

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'En attente de signature',
            self::ACTIF => 'Actif',
            self::SUSPENDU => 'Suspendu',
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
