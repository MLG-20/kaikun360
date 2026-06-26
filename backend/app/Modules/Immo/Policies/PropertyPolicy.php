<?php

namespace App\Modules\Immo\Policies;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Immo\Models\Property;

/**
 * Policy d'accès aux biens immobiliers (phase B2.3).
 *
 * Règle : seul le **propriétaire** d'un bien (ou un **admin**) peut le voir en
 * gestion privée, le modifier et gérer ses documents. Le dépôt est réservé aux
 * rôles `proprietaire` et `admin`. Le `super_admin` passe par Gate::before.
 */
class PropertyPolicy
{
    /**
     * Peut déposer un nouveau bien.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::PROPRIETAIRE->value,
            UserRole::ADMIN->value,
        ]);
    }

    /**
     * Peut consulter le bien en gestion privée (y compris non publié).
     */
    public function view(User $user, Property $property): bool
    {
        return $this->estProprietaireOuAdmin($user, $property);
    }

    /**
     * Peut modifier le bien.
     */
    public function update(User $user, Property $property): bool
    {
        return $this->estProprietaireOuAdmin($user, $property);
    }

    /**
     * Peut gérer (ajouter/consulter) les documents du bien.
     */
    public function manageDocuments(User $user, Property $property): bool
    {
        return $this->estProprietaireOuAdmin($user, $property);
    }

    /**
     * Vrai si l'utilisateur est le propriétaire du bien, ou un admin.
     */
    private function estProprietaireOuAdmin(User $user, Property $property): bool
    {
        return $user->id === $property->owner_id
            || $user->hasRole(UserRole::ADMIN->value);
    }
}
