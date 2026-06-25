<?php

namespace App\Modules\Core\Enums;

/**
 * Type de profil choisi par l'utilisateur à l'inscription (colonne `profiles.type`).
 *
 * Ces 5 types correspondent aux choix proposés au moment du `POST /auth/register`
 * (cf. cahier des charges B1). Ils déterminent l'espace utilisateur et le rôle
 * Spatie attribué par défaut (voir le seeder des rôles en phase B1.2).
 *
 * NB : à ne pas confondre avec les 8 RÔLES de sécurité (UserRole / Spatie).
 * Le type de profil = la "casquette métier" ; le rôle = les droits d'accès.
 */
enum ProfileType: string
{
    case CLIENT = 'client';
    case PROPRIETAIRE = 'proprietaire';
    case PRESTATAIRE = 'prestataire';
    case ENTREPRISE = 'entreprise';
    case DIASPORA = 'diaspora';

    /**
     * Libellé lisible (français).
     */
    public function label(): string
    {
        return match ($this) {
            self::CLIENT => 'Client',
            self::PROPRIETAIRE => 'Propriétaire',
            self::PRESTATAIRE => 'Prestataire',
            self::ENTREPRISE => 'Entreprise',
            self::DIASPORA => 'Diaspora',
        };
    }

    /**
     * Liste des valeurs brutes (pour la validation de l'inscription).
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
