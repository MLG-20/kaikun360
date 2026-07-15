<?php

namespace App\Modules\Build\Enums;

/**
 * Zone géographique du chantier (paramètre du simulateur de budget).
 *
 * Sert de coefficient multiplicateur sur le coût des travaux : construire loin
 * de Dakar renchérit le transport des matériaux (ciment, fer) et la logistique.
 * Le coefficient exact est un réglage (`build.pricing.zone_coeff`) géré au
 * back-office — voir {@see \App\Modules\Build\Services\ConstructionEstimator}.
 */
enum ConstructionZone: string
{
    case DAKAR = 'dakar';
    case AUTRES_REGIONS = 'autres_regions';
    case ZONES_ELOIGNEES = 'zones_eloignees';

    public function label(): string
    {
        return match ($this) {
            self::DAKAR => 'Dakar & Thiès',
            self::AUTRES_REGIONS => 'Autres régions',
            self::ZONES_ELOIGNEES => 'Zones éloignées (Casamance, Sénégal oriental)',
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
