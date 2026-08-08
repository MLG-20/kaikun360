<?php

namespace App\Modules\Assistant\Support;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;

/**
 * Qui parle à l'assistant (phase F10.0).
 *
 * Objet de contexte immuable, construit UNE fois par requête à partir de
 * l'utilisateur authentifié (ou de son absence). Il répond à deux questions,
 * et à elles seules :
 *
 *   1. « Quels outils cette personne a-t-elle le droit d'utiliser ? »
 *      → `role`, résolu ci-dessous.
 *   2. « Au nom de qui les outils doivent-ils s'exécuter ? »
 *      → `user`, transmis tel quel aux policies.
 *
 * ⚠️ Ce contexte ne donne AUCUN droit. Il sert à composer la trousse à outils
 * et à identifier l'appelant ; l'autorisation réelle reste faite par les
 * policies existantes, au moment où l'outil touche une ressource. Un contexte
 * falsifié ne donnerait donc accès à rien de plus qu'un appel HTTP direct.
 */
final class AssistantContext
{
    private function __construct(
        public readonly ?User $user,
        public readonly UserRole $role,
    ) {}

    /**
     * Construit le contexte pour un utilisateur (ou un visiteur anonyme).
     */
    public static function for(?User $user): self
    {
        return new self($user, self::resolveRole($user));
    }

    /**
     * Rôle RETENU pour composer la trousse à outils.
     *
     * Un compte peut cumuler plusieurs rôles (un propriétaire qui est aussi
     * client, par exemple). On retient le plus privilégié : sa trousse contient
     * naturellement les outils des rôles inférieurs, donc rien n'est perdu.
     *
     * L'ordre suit la hiérarchie documentée dans UserRole. `visiteur` est le
     * repli — c'est aussi ce que reçoit un compte sans aucun rôle assigné, ce
     * qui est le comportement sûr : la trousse la plus pauvre.
     */
    private static function resolveRole(?User $user): UserRole
    {
        if ($user === null) {
            return UserRole::VISITEUR;
        }

        $byPrivilegeDesc = [
            UserRole::SUPER_ADMIN,
            UserRole::ADMIN,
            UserRole::AGENT_KAIKUN,
            UserRole::ENTREPRISE,
            UserRole::PRESTATAIRE,
            UserRole::PROPRIETAIRE,
            UserRole::CLIENT,
        ];

        foreach ($byPrivilegeDesc as $role) {
            if ($user->hasRole($role->value)) {
                return $role;
            }
        }

        return UserRole::VISITEUR;
    }

    /**
     * L'appelant est-il authentifié ? (Certains outils n'ont de sens que
     * connecté : ouvrir un fil de support, consulter ses propres dossiers.)
     */
    public function isAuthenticated(): bool
    {
        return $this->user !== null;
    }

    /**
     * L'appelant fait-il partie de l'équipe back-office ?
     */
    public function isStaff(): bool
    {
        return in_array($this->role->value, UserRole::staff(), strict: true);
    }
}
