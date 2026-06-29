<?php

namespace App\Modules\Mobility\Enums;

/**
 * Type de service de mobilité (colonne `mobility_services.type`).
 *
 * Un service de mobilité est un trajet programmé (départ → destination), par
 * opposition à la location d'un véhicule à la journée.
 */
enum MobilityServiceType: string
{
    case NAVETTE = 'navette';         // navette régulière (ex. aéroport)
    case TRANSFERT = 'transfert';     // transfert point à point
    case LIAISON = 'liaison';         // liaison interurbaine
    case EXCURSION = 'excursion';     // excursion / circuit en transport

    public function label(): string
    {
        return match ($this) {
            self::NAVETTE => 'Navette',
            self::TRANSFERT => 'Transfert',
            self::LIAISON => 'Liaison interurbaine',
            self::EXCURSION => 'Excursion',
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
