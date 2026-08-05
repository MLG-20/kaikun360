<?php

namespace App\Enums;

/**
 * Type de service visé par une demande générique (colonne `requests.service_type`).
 *
 * Recouvre les univers Kaikun 360 : une demande transversale peut concerner
 * n'importe lequel d'entre eux.
 *
 * ⚠️ **`BUILD` est HISTORIQUE depuis F8.15.b** — voir le cas ci-dessous.
 */
enum ServiceType: string
{
    case IMMO = 'immo';
    case STAY = 'stay';
    case MANAGE = 'manage';
    /**
     * ⚠️ **Plus aucune demande générique ne peut naître avec ce type**
     * (`StoreRequestRequest` le refuse depuis F8.15.b) : un chantier se dépose
     * sur `POST /construction-requests`, qui ouvre un vrai dossier (estimation,
     * jalons, rapports, devis par lot). Le cas est **conservé** parce que des
     * demandes antérieures le portent en base : le retirer casserait leur
     * relecture. Ne pas le rebrancher sur un formulaire.
     */
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
