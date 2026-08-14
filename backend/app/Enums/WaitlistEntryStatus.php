<?php

namespace App\Enums;

/**
 * Statut de traitement d'une inscription à la liste d'attente (2026-08-14).
 *
 * Cycle simple : NOUVEAU (à la réception) → TRAITE (une fois pris en charge par
 * l'équipe). Pas de machine à états stricte, même logique que
 * `ContactMessageStatus`.
 */
enum WaitlistEntryStatus: string
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
