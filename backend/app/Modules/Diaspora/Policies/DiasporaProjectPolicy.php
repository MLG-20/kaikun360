<?php

namespace App\Modules\Diaspora\Policies;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Diaspora\Models\DiasporaProject;

/**
 * Policy d'accès aux projets diaspora (phase B8.2).
 *
 * Règle : un client diaspora ne voit que SES projets ; l'agent AFFECTÉ y a accès
 * en lecture/écriture. Les admins ont accès (super_admin via Gate::before).
 * L'affectation d'un agent relève du back-office (admin).
 */
class DiasporaProjectPolicy
{
    /**
     * Déposer un projet : tout utilisateur authentifié (client diaspora).
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Consultation : client propriétaire, agent affecté ou admin.
     */
    public function view(User $user, DiasporaProject $project): bool
    {
        return $this->isOwnerOrAssignedAgent($user, $project)
            || $user->hasRole(UserRole::ADMIN->value);
    }

    /**
     * Écriture (rapports, statut) : agent affecté ou admin.
     */
    public function update(User $user, DiasporaProject $project): bool
    {
        return ($project->agent_id !== null && $user->id === $project->agent_id)
            || $user->hasRole(UserRole::ADMIN->value);
    }

    /**
     * Affecter un agent : back-office (admin).
     */
    public function assign(User $user, DiasporaProject $project): bool
    {
        return $user->hasRole(UserRole::ADMIN->value);
    }

    private function isOwnerOrAssignedAgent(User $user, DiasporaProject $project): bool
    {
        return $user->id === $project->client_id
            || ($project->agent_id !== null && $user->id === $project->agent_id);
    }
}
