<?php

namespace App\Enums;

/**
 * Statut de la caution d'une réservation (colonne `bookings.caution_status`).
 *
 * Introduit pour les locations de véhicule (B7.4) ; transversal car la caution
 * concerne potentiellement d'autres réservations (nuitées, etc.).
 */
enum CautionStatus: string
{
    case RETENUE = 'retenue';     // caution prélevée/bloquée
    case RESTITUEE = 'restituee'; // caution rendue (annulation conforme / fin sans incident)
    case PERDUE = 'perdue';       // caution conservée (annulation tardive / incident)

    public function label(): string
    {
        return match ($this) {
            self::RETENUE => 'Retenue',
            self::RESTITUEE => 'Restituée',
            self::PERDUE => 'Perdue',
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
