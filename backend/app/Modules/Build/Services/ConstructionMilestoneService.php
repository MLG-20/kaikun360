<?php

namespace App\Modules\Build\Services;

use App\Modules\Build\Enums\ConstructionObjective;
use App\Modules\Build\Enums\MilestoneStatus;
use App\Modules\Build\Models\ConstructionMilestone;
use App\Modules\Build\Models\ConstructionRequest;
use Illuminate\Support\Collection;

/**
 * Logique des jalons de chantier (phase B5.3).
 *
 * Fournit un découpage type en étapes selon l'objectif du projet et matérialise
 * ces étapes en base pour une demande donnée. Le découpage « neuf » comporte les
 * fondations et la toiture, absentes du parcours « rénovation ».
 */
class ConstructionMilestoneService
{
    /**
     * Étapes type par objectif (ordre = exécution du chantier).
     *
     * @return list<string>
     */
    public function defaultStagesFor(ConstructionObjective $objective): array
    {
        return match ($objective) {
            ConstructionObjective::RENOVATION => [
                'Diagnostic',
                'Démolition / dépose',
                'Gros œuvre',
                'Second œuvre',
                'Finitions',
                'Livraison',
            ],
            ConstructionObjective::CONSTRUCTION_NEUVE,
            ConstructionObjective::EXTENSION => [
                'Études & permis',
                'Fondations',
                'Gros œuvre',
                'Toiture',
                'Second œuvre',
                'Finitions',
                'Livraison',
            ],
        };
    }

    /**
     * Crée en base les jalons par défaut d'une demande (statut « à venir »).
     *
     * Idempotent au sens métier : ne fait rien si la demande a déjà des jalons.
     *
     * @return Collection<int, ConstructionMilestone>
     */
    public function seedDefault(ConstructionRequest $request): Collection
    {
        if ($request->milestones()->exists()) {
            return $request->milestones()->get();
        }

        $stages = $this->defaultStagesFor($request->objective);

        $milestones = collect($stages)->map(function (string $name, int $index) use ($request) {
            return $request->milestones()->create([
                'name' => $name,
                'position' => $index + 1,
                'status' => MilestoneStatus::A_VENIR->value,
            ]);
        });

        return $milestones;
    }
}
