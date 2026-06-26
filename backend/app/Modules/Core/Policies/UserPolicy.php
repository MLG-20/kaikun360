<?php

namespace App\Modules\Core\Policies;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;

/**
 * Policy d'accès aux données d'un utilisateur (phase B1.5).
 *
 * Règle de base : un utilisateur ne peut consulter/modifier que SON propre
 * compte. Les rôles admin et super_admin font exception (le super_admin passe
 * de toute façon par le bypass Gate::before).
 *
 * Les endpoints /users/me étant déjà auto-restreints à l'utilisateur connecté,
 * cette policy sécurisera surtout les accès inter-utilisateurs du back-office
 * (phase B13). Elle est testée dès maintenant pour figer le contrat.
 */
class UserPolicy
{
    /**
     * Peut consulter le profil de $target.
     */
    public function viewProfile(User $authUser, User $target): bool
    {
        return $this->estLuiMemeOuAdmin($authUser, $target);
    }

    /**
     * Peut modifier le profil de $target.
     */
    public function updateProfile(User $authUser, User $target): bool
    {
        return $this->estLuiMemeOuAdmin($authUser, $target);
    }

    /**
     * Peut gérer (déposer/consulter) les documents de $target.
     */
    public function manageDocuments(User $authUser, User $target): bool
    {
        return $this->estLuiMemeOuAdmin($authUser, $target);
    }

    /**
     * Vrai si l'utilisateur agit sur lui-même, ou s'il est admin.
     * (Le super_admin est déjà couvert par Gate::before.)
     */
    private function estLuiMemeOuAdmin(User $authUser, User $target): bool
    {
        return $authUser->id === $target->id
            || $authUser->hasAnyRole([UserRole::ADMIN->value, UserRole::SUPER_ADMIN->value]);
    }
}
