<?php

namespace App\Modules\Build\Enums;

/**
 * Statut d'une demande de construction (colonne `construction_requests.status`).
 *
 * Cycle métier indicatif :
 *   SOUMISE → EN_ETUDE → DEVIS_ENVOYE → ACCEPTEE → EN_CHANTIER → TERMINEE
 * (ANNULEE possible à tout moment). Les transitions fines relèveront de la
 * couche transversale Requests/Quotes (phase B11).
 */
enum ConstructionRequestStatus: string
{
    case SOUMISE = 'soumise';
    case EN_ETUDE = 'en_etude';
    case DEVIS_ENVOYE = 'devis_envoye';
    case ACCEPTEE = 'acceptee';
    case EN_CHANTIER = 'en_chantier';
    case TERMINEE = 'terminee';
    case ANNULEE = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::SOUMISE => 'Soumise',
            self::EN_ETUDE => 'En étude',
            self::DEVIS_ENVOYE => 'Devis envoyé',
            self::ACCEPTEE => 'Acceptée',
            self::EN_CHANTIER => 'En chantier',
            self::TERMINEE => 'Terminée',
            self::ANNULEE => 'Annulée',
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
