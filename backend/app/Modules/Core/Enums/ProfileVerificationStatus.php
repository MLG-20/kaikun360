<?php

namespace App\Modules\Core\Enums;

/**
 * État de vérification (KYC) d'un profil (colonne `profiles.verification_status`).
 *
 * Formalise les valeurs déjà utilisées depuis B1. Sert notamment à autoriser la
 * publication par les prestataires VÉRIFIÉS (modules Explore B6, Pro B10).
 */
enum ProfileVerificationStatus: string
{
    case NON_VERIFIE = 'non_verifie';
    case EN_COURS = 'en_cours';
    case VERIFIE = 'verifie';
    case REJETE = 'rejete';

    public function label(): string
    {
        return match ($this) {
            self::NON_VERIFIE => 'Non vérifié',
            self::EN_COURS => 'En cours de vérification',
            self::VERIFIE => 'Vérifié',
            self::REJETE => 'Rejeté',
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
