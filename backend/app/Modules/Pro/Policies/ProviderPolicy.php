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

    /**
     * Affecter une mission à un prestataire : back-office.
     *
     * ⚠️ F7.4.b — la garde était le RÔLE `admin`, ce qui contredisait le
     * cahier des charges §7 : « Agent Kaikun → traitement demandes, validation
     * de base, **affectation prestataire** ». Un agent se prenait un 403 sur un
     * geste que le CDC lui confie explicitement. C'est désormais une
     * PERMISSION (`traiter:demandes`), donc délégable personne par personne
     * comme le reste du back-office depuis F7.1.b — l'admin la détient déjà en
     * bloc via son rôle, rien ne change pour lui.
     *
     * Même correction que celle déjà appliquée aux chantiers en F7.3.e3, où
     * l'affectation des prestataires BTP est gardée par `gerer:chantiers`.
     */
    public function assignMission(User $user, Provider $provider): bool
    {
        return $user->can('traiter:demandes');
    }
}
