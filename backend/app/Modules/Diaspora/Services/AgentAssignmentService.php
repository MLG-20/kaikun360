<?php

namespace App\Modules\Diaspora\Services;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Diaspora\Enums\DiasporaProjectStatus;
use App\Modules\Diaspora\Models\DiasporaProject;

/**
 * Logique d'attribution d'un agent dédié à un projet diaspora (phase B8.2).
 *
 * Affecte un agent explicite, ou — à défaut — l'agent le MOINS CHARGÉ (celui qui
 * suit le moins de projets diaspora actifs), pour répartir les dossiers. Passe le
 * projet « en cours ».
 */
class AgentAssignmentService
{
    /**
     * Affecte un agent au projet (explicite ou auto) et le passe en cours.
     */
    public function assign(DiasporaProject $project, ?User $agent = null): DiasporaProject
    {
        $agent ??= $this->leastBusyAgent();

        $project->update([
            'agent_id' => $agent?->id,
            'status' => DiasporaProjectStatus::EN_COURS->value,
        ]);

        return $project->refresh();
    }

    /**
     * Agent (rôle agent_kaikun) suivant le moins de projets diaspora actifs.
     *
     * La charge est calculée par requête (sans relation sur User) pour ne pas
     * coupler le module Core au module Diaspora.
     */
    public function leastBusyAgent(): ?User
    {
        $agents = User::role(UserRole::AGENT_KAIKUN->value)->get();

        if ($agents->isEmpty()) {
            return null;
        }

        // Nombre de projets actifs par agent (clé = agent_id).
        $activeCounts = DiasporaProject::query()
            ->where('status', DiasporaProjectStatus::EN_COURS->value)
            ->whereIn('agent_id', $agents->pluck('id'))
            ->selectRaw('agent_id, COUNT(*) as total')
            ->groupBy('agent_id')
            ->pluck('total', 'agent_id');

        return $agents->sortBy(fn (User $agent) => $activeCounts[$agent->id] ?? 0)->first();
    }
}
