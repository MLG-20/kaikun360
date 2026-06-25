<?php

namespace App\Modules\Core\Enums;

/**
 * Les 8 rôles de sécurité Kaikun, gérés par Spatie Permission.
 *
 * C'est la SOURCE DE VÉRITÉ des droits d'accès (à ne pas confondre avec
 * ProfileType, la "casquette métier"). Un utilisateur peut techniquement
 * cumuler plusieurs rôles, mais reçoit un rôle par défaut à l'inscription
 * selon son type de profil (voir defaultForProfileType()).
 *
 * Hiérarchie indicative (du moins au plus privilégié) :
 *   visiteur < client/proprietaire/prestataire/entreprise < agent_kaikun < admin < super_admin
 */
enum UserRole: string
{
    case VISITEUR = 'visiteur';          // non authentifié (conceptuel, rarement assigné)
    case CLIENT = 'client';
    case PROPRIETAIRE = 'proprietaire';
    case PRESTATAIRE = 'prestataire';
    case ENTREPRISE = 'entreprise';
    case AGENT_KAIKUN = 'agent_kaikun';  // agent opérationnel (validation, suivi)
    case ADMIN = 'admin';
    case SUPER_ADMIN = 'super_admin';    // accès total (bypass via Gate::before)

    /**
     * Libellé lisible (français).
     */
    public function label(): string
    {
        return match ($this) {
            self::VISITEUR => 'Visiteur',
            self::CLIENT => 'Client',
            self::PROPRIETAIRE => 'Propriétaire',
            self::PRESTATAIRE => 'Prestataire',
            self::ENTREPRISE => 'Entreprise',
            self::AGENT_KAIKUN => 'Agent Kaikun',
            self::ADMIN => 'Admin',
            self::SUPER_ADMIN => 'Super Admin',
        };
    }

    /**
     * Rôle attribué par défaut à l'inscription selon le type de profil choisi.
     *
     * Le profil "diaspora" est un client résidant à l'étranger : il reçoit donc
     * le rôle CLIENT (ses projets diaspora sont une fonctionnalité, pas un rôle).
     */
    public static function defaultForProfileType(ProfileType $type): self
    {
        return match ($type) {
            ProfileType::CLIENT, ProfileType::DIASPORA => self::CLIENT,
            ProfileType::PROPRIETAIRE => self::PROPRIETAIRE,
            ProfileType::PRESTATAIRE => self::PRESTATAIRE,
            ProfileType::ENTREPRISE => self::ENTREPRISE,
        };
    }

    /**
     * Liste des valeurs brutes.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
