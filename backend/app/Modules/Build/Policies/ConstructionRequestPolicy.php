<?php

namespace App\Modules\Build\Policies;

use App\Models\User;
use App\Modules\Build\Models\ConstructionRequest;
use App\Modules\Core\Enums\UserRole;

/**
 * Policy d'accès aux demandes de construction (phase B5.5).
 *
 * Règle : un client ne voit que SES propres demandes (et leurs rapports). Les
 * agents et admins y ont accès pour le suivi ; super_admin via Gate::before.
 */
class ConstructionRequestPolicy
{
    /**
     * Tout utilisateur authentifié peut déposer une demande de construction.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Consultation : propriétaire de la demande ou agent/admin.
     */
    public function view(User $user, ConstructionRequest $request): bool
    {
        return $user->id === $request->client_id
            || $user->hasAnyRole([
                UserRole::AGENT_KAIKUN->value,
                UserRole::ADMIN->value,
            ]);
    }
}
