<?php

namespace App\Modules\Build\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Build\Enums\ConstructionObjective;
use App\Modules\Build\Enums\ConstructionQuoteStatus;
use App\Modules\Build\Enums\ConstructionRequestStatus;
use App\Modules\Build\Enums\ConstructionZone;
use App\Modules\Build\Enums\FinishLevel;
use App\Modules\Build\Http\Requests\SimulateConstructionRequest;
use App\Modules\Build\Http\Requests\StoreConstructionRequestRequest;
use App\Modules\Build\Http\Resources\ConstructionRequestResource;
use App\Modules\Build\Http\Resources\ReportResource;
use App\Modules\Build\Models\ConstructionRequest;
use App\Modules\Build\Services\ConstructionEstimator;
use App\Modules\Build\Services\ConstructionMilestoneService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Demandes de construction — espace client (phase B5.5).
 *
 * Un client dépose une demande (estimation auto + jalons par défaut), consulte
 * ses demandes et leurs rapports de suivi. L'isolation est garantie par le
 * scoping `client_id` (liste) et la `ConstructionRequestPolicy` (détail).
 */
class ConstructionRequestController extends Controller
{
    /**
     * Dépose une demande de construction. POST /api/v1/construction-requests
     *
     * À la création : calcul de l'estimation indicative (simulateur) et
     * génération des jalons de chantier par défaut.
     */
    public function store(
        StoreConstructionRequestRequest $request,
        ConstructionEstimator $estimator,
        ConstructionMilestoneService $milestones
    ): JsonResponse {
        $data = $request->validated();

        $objective = ConstructionObjective::from($data['objective']);
        $finishLevel = FinishLevel::from($data['finish_level']);

        $constructionRequest = ConstructionRequest::create($data + [
            'reference' => 'CST-'.Str::upper(Str::random(8)),
            'client_id' => $request->user()->id,
            'status' => ConstructionRequestStatus::SOUMISE->value,
            'estimated_cost_xof' => $estimator->estimate($objective, (int) $data['surface_m2'], $finishLevel),
        ]);

        // Jalons par défaut selon l'objectif du projet.
        $milestones->seedDefault($constructionRequest);

        return ApiResponse::created([
            'construction_request' => ConstructionRequestResource::make(
                $constructionRequest->load('milestones')
            ),
        ]);
    }

    /**
     * Mes demandes de construction. GET /api/v1/construction-requests/mine
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        $requests = ConstructionRequest::where('client_id', $request->user()->id)
            ->withCount('reports')
            // F3.9 — Les devis voyagent avec le chantier : l'écran client doit
            // pouvoir afficher « un devis vous attend » sans un aller-retour par
            // dossier. Les BROUILLONS sont exclus ici comme ils le sont dans
            // `ConstructionQuoteController::index` — un chiffrage en cours de
            // composition n'est pas un document du client.
            ->with(['quotes' => fn ($q) => $q->where('status', '!=', ConstructionQuoteStatus::BROUILLON->value)])
            ->latest()
            ->paginate(15);

        return ConstructionRequestResource::collection($requests);
    }

    /**
     * Détail d'une demande (avec jalons). GET /api/v1/construction-requests/{constructionRequest}
     */
    public function show(ConstructionRequest $constructionRequest): JsonResponse
    {
        Gate::authorize('view', $constructionRequest);

        // F7.3.b — `client` chargé pour la fiche back-office (qui a déposé, et
        // comment le joindre) ; les jalons sont triés par leur position, l'ordre
        // du chantier étant ce que l'écran restitue.
        $constructionRequest->load([
            'client',
            'milestones' => fn ($query) => $query->orderBy('position'),
        ])->loadCount('reports');

        return ApiResponse::success([
            'construction_request' => ConstructionRequestResource::make($constructionRequest),
        ]);
    }

    /**
     * Rapports de suivi d'une demande. GET /api/v1/construction-requests/{constructionRequest}/reports
     */
    public function reports(ConstructionRequest $constructionRequest): AnonymousResourceCollection
    {
        Gate::authorize('view', $constructionRequest);

        $reports = $constructionRequest->reports()
            ->latest('reported_at')
            ->paginate(15);

        return ReportResource::collection($reports);
    }

    /**
     * Simulation de budget (sans persistance). POST /api/v1/construction-requests/simulate
     *
     * Endpoint PUBLIC (aucune donnée personnelle, pur calcul) : la page
     * Construction du site l'appelle sans compte. Renvoie le détail complet
     * (travaux, frais annexes, foncier, délai, jalons, rentabilité).
     */
    public function simulate(SimulateConstructionRequest $request, ConstructionEstimator $estimator): JsonResponse
    {
        $data = $request->validated();

        return ApiResponse::success([
            'simulation' => $estimator->breakdown(
                ConstructionObjective::from($data['objective']),
                (int) $data['surface_m2'],
                FinishLevel::from($data['finish_level']),
                (int) ($data['levels'] ?? 1),
                ConstructionZone::from($data['zone'] ?? ConstructionZone::DAKAR->value),
                (int) ($data['land_cost_xof'] ?? 0),
            ),
        ]);
    }
}
