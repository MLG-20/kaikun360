<?php

namespace App\Modules\TeamBuilding\Enums;

/**
 * Statut d'une demande de team building (colonne `team_building_requests.status`).
 *
 * Cycle : NOUVEAU (déposé) → EN_ETUDE (admin compose le devis) → DEVIS_ENVOYE →
 * ACCEPTE (suivi opérationnel) ; ANNULE possible à tout moment.
 */
enum TeamBuildingRequestStatus: string
{
    case NOUVEAU = 'nouveau';
    case EN_ETUDE = 'en_etude';
    case DEVIS_ENVOYE = 'devis_envoye';
    case ACCEPTE = 'accepte';
    case ANNULE = 'annule';

    public function label(): string
    {
        return match ($this) {
            self::NOUVEAU => 'Nouveau',
            self::EN_ETUDE => 'En étude',
            self::DEVIS_ENVOYE => 'Devis envoyé',
            self::ACCEPTE => 'Accepté',
            self::ANNULE => 'Annulé',
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
