<?php

namespace App\Modules\TeamBuilding\Policies;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\TeamBuilding\Models\TeamBuildingRequest;

/**
 * Policy d'accès aux demandes/devis de team building (phase B9.3).
 *
 * Règle : une entreprise ne voit que SES demandes et devis. La composition et
 * l'envoi des devis relèvent du back-office (admin) ; l'acceptation appartient à
 * l'entreprise. super_admin passe par Gate::before.
 */
class TeamBuildingRequestPolicy
{
    /**
     * Déposer une demande : tout utilisateur authentifié (entreprise).
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Consulter une demande/ses devis : entreprise propriétaire ou admin.
     */
    public function view(User $user, TeamBuildingRequest $request): bool
    {
        return $user->id === $request->company_id
            || $user->hasRole(UserRole::ADMIN->value);
    }

    /**
     * Composer / envoyer un devis : back-office (admin).
     */
    public function manage(User $user, TeamBuildingRequest $request): bool
    {
        return $user->hasRole(UserRole::ADMIN->value);
    }

    /**
     * Accepter un devis : entreprise propriétaire de la demande.
     */
    public function accept(User $user, TeamBuildingRequest $request): bool
    {
        return $user->id === $request->company_id;
    }
}
