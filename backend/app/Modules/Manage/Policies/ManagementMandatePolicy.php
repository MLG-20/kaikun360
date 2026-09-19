<?php

namespace App\Modules\Manage\Policies;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Manage\Models\ManagementMandate;

/**
 * Policy d'accès aux données de gestion locative (phase B4.5).
 *
 * Règle : un propriétaire ne voit que les mandats de SES biens. Les agents et
 * admins (gestion pour le compte de Kaikun) y ont accès ; super_admin via Gate::before.
 */
class ManagementMandatePolicy
{
    public function view(User $user, ManagementMandate $mandate): bool
    {
        return $user->id === $mandate->owner_id
            || $user->hasAnyRole([
                UserRole::AGENT_KAIKUN->value,
                UserRole::ADMIN->value,
            ]);
    }

    /**
     * Modifier ou supprimer un mandat : son propriétaire seulement (F21.1).
     *
     * Le super_admin ne gère ainsi que les mandats de SES propres biens, jamais
     * ceux d'un propriétaire externe (le passe-droit global de `Gate::before`
     * est écarté pour `update`, cf. AppServiceProvider).
     */
    public function update(User $user, ManagementMandate $mandate): bool
    {
        return $user->id === $mandate->owner_id;
    }
}
