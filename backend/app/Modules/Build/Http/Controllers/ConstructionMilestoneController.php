<?php

namespace App\Modules\Build\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Build\Enums\MilestoneStatus;
use App\Modules\Build\Http\Requests\ReorderMilestonesRequest;
use App\Modules\Build\Http\Requests\StoreMilestoneRequest;
use App\Modules\Build\Http\Requests\UpdateMilestoneRequest;
use App\Modules\Build\Http\Resources\ConstructionMilestoneResource;
use App\Modules\Build\Models\ConstructionMilestone;
use App\Modules\Build\Models\ConstructionRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Pilotage des jalons de chantier par les AGENTS (phase F7.3.e1).
 *
 * Jusqu'ici les jalons étaient semés au dépôt de la demande (B5.3) puis FIGÉS :
 * aucun endpoint ne permettait de les faire avancer, alors que « jalons chantier »
 * est une fonction explicite du module Construction du cahier des charges (§6).
 * Ce contrôleur comble ce trou et couvre les deux gestes du terrain :
 *   - faire AVANCER une étape (à venir → en cours → terminé) ;
 *   - REPLANIFIER le chantier (ajouter, renommer, redater, réordonner, retirer),
 *     car aucun chantier ne suit exactement le gabarit posé à la création.
 *
 * Réservé à la permission `gerer:chantiers` (middleware `can:` sur les routes),
 * comme la publication des rapports de suivi.
 */
class ConstructionMilestoneController extends Controller
{
    /**
     * Ajoute un jalon au planning.
     * POST /api/v1/construction-requests/{constructionRequest}/milestones
     */
    public function store(StoreMilestoneRequest $request, ConstructionRequest $constructionRequest): JsonResponse
    {
        $data = $request->validated();

        // Position omise → le jalon s'ajoute en fin de planning.
        $data['position'] ??= (int) $constructionRequest->milestones()->max('position') + 1;
        $data['status'] ??= MilestoneStatus::A_VENIR->value;

        /** @var ConstructionMilestone $milestone */
        $milestone = $constructionRequest->milestones()->create(
            $this->withCoherentActualDate($data, null)
        );

        return ApiResponse::created(['milestone' => ConstructionMilestoneResource::make($milestone)]);
    }

    /**
     * Fait avancer ou replanifie un jalon.
     * PATCH /api/v1/construction-milestones/{milestone}
     */
    public function update(UpdateMilestoneRequest $request, ConstructionMilestone $milestone): JsonResponse
    {
        $milestone->update(
            $this->withCoherentActualDate($request->validated(), $milestone)
        );

        return ApiResponse::success(['milestone' => ConstructionMilestoneResource::make($milestone->fresh())]);
    }

    /**
     * Retire un jalon du planning.
     * DELETE /api/v1/construction-milestones/{milestone}
     *
     * Les positions restantes ne sont PAS recompactées : l'affichage trie par
     * `position`, un trou est donc invisible, et recompacter obligerait à réécrire
     * toute la liste à chaque suppression.
     */
    public function destroy(ConstructionMilestone $milestone): JsonResponse
    {
        $milestone->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    /**
     * Réécrit l'ordre du planning.
     * PUT /api/v1/construction-requests/{constructionRequest}/milestones/reorder
     *
     * Le corps porte la liste ORDONNÉE des identifiants. Tout identifiant
     * étranger au chantier fait échouer la requête entière (422) : accepter un
     * ordre partiel laisserait un planning à moitié réordonné.
     */
    public function reorder(ReorderMilestonesRequest $request, ConstructionRequest $constructionRequest): JsonResponse
    {
        /** @var list<int> $ordered */
        $ordered = $request->validated()['milestones'];

        $owned = $constructionRequest->milestones()->pluck('id')->all();

        if (array_diff($ordered, $owned) !== []) {
            return ApiResponse::error(
                'Un des jalons transmis n’appartient pas à ce chantier.',
                422
            );
        }

        DB::transaction(function () use ($ordered, $constructionRequest) {
            foreach ($ordered as $index => $id) {
                $constructionRequest->milestones()
                    ->whereKey($id)
                    ->update(['position' => $index + 1]);
            }
        });

        $milestones = $constructionRequest->milestones()->orderBy('position')->get();

        return ApiResponse::success([
            'milestones' => ConstructionMilestoneResource::collection($milestones),
        ]);
    }

    /**
     * Maintient la cohérence entre le statut et la date de réalisation.
     *
     * Un jalon « terminé » sans date d'achèvement ne vaut rien pour un suivi de
     * chantier (ni pour un litige) : on la date du jour si l'agent ne l'a pas
     * saisie. À l'inverse, rouvrir un jalon (retour à venir / en cours) EFFACE la
     * date réelle, sinon l'écran afficherait une étape en cours « achevée le … ».
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withCoherentActualDate(array $data, ?ConstructionMilestone $current): array
    {
        if (! array_key_exists('status', $data)) {
            return $data;
        }

        $status = $data['status'] instanceof MilestoneStatus
            ? $data['status']
            : MilestoneStatus::from($data['status']);

        if ($status === MilestoneStatus::TERMINE) {
            // Date explicitement fournie ? on la respecte. Sinon on garde celle
            // déjà en base, et à défaut on prend aujourd'hui.
            $data['actual_date'] ??= $current?->actual_date ?? now()->toDateString();

            return $data;
        }

        $data['actual_date'] = null;

        return $data;
    }
}
