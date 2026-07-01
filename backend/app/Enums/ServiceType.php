<?php

namespace App\Enums;

/**
 * Type de service visé par une demande générique (colonne `requests.service_type`).
 *
 * Recouvre les univers Kaikun 360 : une demande transversale peut concerner
 * n'importe lequel d'entre eux.
 */
enum ServiceType: string
{
    case IMMO = 'immo';
    case STAY = 'stay';
    case MANAGE = 'manage';
    case BUILD = 'build';
    case EXPLORE = 'explore';
    case MOBILITY = 'mobility';
    case DIASPORA = 'diaspora';
    case TEAM_BUILDING = 'team_building';
    case PRO = 'pro';
    case AUTRE = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::IMMO => 'Immobilier',
            self::STAY => 'Nuitées',
            self::MANAGE => 'Gestion locative',
            self::BUILD => 'Construction',
            self::EXPLORE => 'Tourisme & expériences',
            self::MOBILITY => 'Transport & mobilité',
            self::DIASPORA => 'Diaspora',
            self::TEAM_BUILDING => 'Team building',
            self::PRO => 'Prestataires',
            self::AUTRE => 'Autre',
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
