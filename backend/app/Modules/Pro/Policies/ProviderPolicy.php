<?php

namespace App\Modules\Pro\Policies;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Pro\Models\Provider;

/**
 * Policy du profil prestataire marketplace (phase B10.2).
 *
 * Un utilisateur gère son propre profil prestataire ; les admins y ont accès.
 * La règle « un prestataire non validé ne publie aucun service public » est
 * appliquée en amont via le `verification_status` du profil (piloté par la
 * validation Provider) dans les policies Explore (B6) et Mobility (B7).
 */
class ProviderPolicy
{
    /**
     * Consulter/gérer son profil prestataire : son propriétaire ou un admin.
     */
    public function view(User $user, Provider $provider): bool
    {
        return $user->id === $provider->user_id
            || $user->hasRole(UserRole::ADMIN->value);
    }

    public function update(User $user, Provider $provider): bool
    {
        return $user->id === $provider->user_id
            || $user->hasRole(UserRole::ADMIN->value);
    }
}
