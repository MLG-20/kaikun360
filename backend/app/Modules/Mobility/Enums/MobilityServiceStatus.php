<?php

namespace App\Modules\Mobility\Enums;

/**
 * Statuts du cycle de vie d'un service de mobilité (table `mobility_services`).
 */
enum MobilityServiceStatus: string
{
    case EN_ATTENTE_VALIDATION = 'en_attente_validation';
    case PUBLIE = 'publie';
    case SUSPENDU = 'suspendu';
    case REJETE = 'rejete';

    /**
     * Retiré par le prestataire (F8.23).
     *
     * ⚠️ Distinct de `REJETE` (décision de l'équipe) et de `SUSPENDU` (mesure
     * subie) : `RETIRE` est un geste volontaire du prestataire sur un départ
     * qui a déjà servi et qu'on ne peut donc pas supprimer. Aligné sur
     * `VehicleStatus::RETIRE`, pour que le retrait d'une offre se lise partout
     * de la même façon (cf. `OfferRetirementService`).
     */
    case RETIRE = 'retire';

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE_VALIDATION => 'En attente de validation',
            self::PUBLIE => 'Publié',
            self::SUSPENDU => 'Suspendu',
            self::REJETE => 'Rejeté',
            self::RETIRE => 'Retiré du catalogue',
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
