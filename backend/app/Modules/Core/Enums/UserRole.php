<?php

namespace App\Modules\Core\Enums;

/**
 * Les 9 rôles de sécurité Kaikun, gérés par Spatie Permission.
 *
 * C'est la SOURCE DE VÉRITÉ des droits d'accès (à ne pas confondre avec
 * ProfileType, la "casquette métier"). Un utilisateur peut techniquement
 * cumuler plusieurs rôles, mais reçoit un rôle par défaut à l'inscription
 * selon son type de profil (voir defaultForProfileType()).
 *
 * Hiérarchie indicative (du moins au plus privilégié) :
 *   visiteur < client/proprietaire/prestataire/entreprise/diaspora < agent_kaikun < admin < super_admin
 */
enum UserRole: string
{
    case VISITEUR = 'visiteur';          // non authentifié (conceptuel, rarement assigné)
    case CLIENT = 'client';
    case PROPRIETAIRE = 'proprietaire';
    case PRESTATAIRE = 'prestataire';
    case ENTREPRISE = 'entreprise';
    case DIASPORA = 'diaspora';           // client résidant à l'étranger, espace connecté à part
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
            self::DIASPORA => 'Diaspora',
            self::AGENT_KAIKUN => 'Agent Kaikun',
            self::ADMIN => 'Admin',
            self::SUPER_ADMIN => 'Super Admin',
        };
    }

    /**
     * Rôle attribué par défaut à l'inscription selon le type de profil choisi.
     *
     * Depuis la séparation de l'espace diaspora (2026-08-22), le profil
     * "diaspora" reçoit son PROPRE rôle, au même titre que les autres
     * profils métier — il a son espace connecté indépendant et n'a plus
     * accès à l'espace client.
     */
    public static function defaultForProfileType(ProfileType $type): self
    {
        return match ($type) {
            ProfileType::CLIENT => self::CLIENT,
            ProfileType::PROPRIETAIRE => self::PROPRIETAIRE,
            ProfileType::PRESTATAIRE => self::PRESTATAIRE,
            ProfileType::ENTREPRISE => self::ENTREPRISE,
            ProfileType::DIASPORA => self::DIASPORA,
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

    /**
     * Rôles de l'ÉQUIPE back-office (« poste de commandement », F7.1).
     *
     * Ce sont les seuls comptes qui accèdent au back-office : l'agent opérationnel
     * (sous-admin), l'administrateur et le super administrateur. Les rôles publics
     * (client, propriétaire…) en sont exclus. Sert à filtrer l'annuaire de l'équipe
     * et à garder les routes du back-office.
     *
     * @return array<int, string>
     */
    public static function staff(): array
    {
        return [
            self::AGENT_KAIKUN->value,
            self::ADMIN->value,
            self::SUPER_ADMIN->value,
        ];
    }

    /**
     * Rôles qu'un administrateur peut ATTRIBUER à un membre de l'équipe (F7.1.a).
     *
     * On n'attribue jamais `super_admin` via l'interface : c'est le compte racine,
     * créé par amorçage (seeder) et jamais dégradé/créé depuis le back-office.
     * `admin` reste attribuable, mais uniquement par un super_admin (garde-fou
     * d'escalade vérifié dans le contrôleur).
     *
     * @return array<int, string>
     */
    public static function assignableStaff(): array
    {
        return [
            self::AGENT_KAIKUN->value,
            self::ADMIN->value,
        ];
    }

    /**
     * Rôles pour lesquels la double authentification est OBLIGATOIRE (F7.1.d).
     *
     * Les comptes à fort privilège (admin, super_admin) doivent valider un second
     * facteur (code e-mail) à la connexion : ce sont eux qui pilotent la
     * plateforme. L'agent (sous-admin) n'y est pas soumis pour l'instant — le
     * périmètre peut être élargi ici sans toucher au reste du flux.
     *
     * @return array<int, string>
     */
    public static function twoFactorRequired(): array
    {
        return [
            self::ADMIN->value,
            self::SUPER_ADMIN->value,
        ];
    }
}
