<?php

namespace App\Enums;

/**
 * Statut de traitement d'un message de contact (F2.8.1).
 *
 * Cycle simple : NOUVEAU (à la réception) → TRAITE (une fois pris en charge par
 * l'équipe). Pas de machine à états stricte : un message peut être basculé
 * librement entre les deux états.
 */
enum ContactMessageStatus: string
{
    case NOUVEAU = 'nouveau';
    case TRAITE = 'traite';

    /**
     * Libellé lisible (français).
     */
    public function label(): string
    {
        return match ($this) {
            self::NOUVEAU => 'Nouveau',
            self::TRAITE => 'Traité',
        };
    }
}
