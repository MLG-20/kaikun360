<?php

namespace App\Modules\Core\Enums;

/**
 * Types de pièces justificatives qu'un utilisateur peut déposer (KYC).
 *
 * Liste volontairement ouverte sur "autre" pour ne pas bloquer les cas
 * particuliers ; les types spécifiques (BTP, transport...) s'ajouteront
 * au fil des modules métier.
 */
enum DocumentType: string
{
    case CNI = 'cni';                                   // carte nationale d'identité
    case PASSEPORT = 'passeport';
    case PERMIS_CONDUIRE = 'permis_conduire';
    case JUSTIFICATIF_DOMICILE = 'justificatif_domicile';
    case REGISTRE_COMMERCE = 'registre_commerce';       // entreprise / prestataire
    case AUTRE = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::CNI => "Carte nationale d'identité",
            self::PASSEPORT => 'Passeport',
            self::PERMIS_CONDUIRE => 'Permis de conduire',
            self::JUSTIFICATIF_DOMICILE => 'Justificatif de domicile',
            self::REGISTRE_COMMERCE => 'Registre de commerce',
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
