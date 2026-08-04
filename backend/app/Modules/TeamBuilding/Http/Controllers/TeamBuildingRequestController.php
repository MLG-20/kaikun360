<?php

namespace App\Modules\TeamBuilding\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TeamBuilding\Enums\TeamBuildingRequestStatus;
use App\Modules\TeamBuilding\Events\TeamBuildingRequestCreated;
use App\Modules\TeamBuilding\Http\Requests\StoreTeamBuildingRequestRequest;
use App\Modules\TeamBuilding\Http\Resources\TeamBuildingRequestResource;
use App\Modules\TeamBuilding\Models\TeamBuildingRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Demandes de team building — dépôt entreprise, suivi et file back-office (B9.3).
 */
class TeamBuildingRequestController extends Controller
{
    /**
     * Dépose une demande. POST /api/v1/team-building-requests
     */
    public function store(StoreTeamBuildingRequestRequest $request): JsonResponse
    {
        $tbRequest = TeamBuildingRequest::create($request->validated() + [
            'reference' => 'TBR-'.Str::upper(Str::random(8)),
            'company_id' => $request->user()->id,
            'status' => TeamBuildingRequestStatus::NOUVEAU->value,
        ]);

        // Alimente la file d'attente admin dédiée.
        TeamBuildingRequestCreated::dispatch($tbRequest);

        return ApiResponse::created(['request' => TeamBuildingRequestResource::make($tbRequest)]);
    }

    /**
     * Mes demandes. GET /api/v1/team-building-requests/mine
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        $requests = TeamBuildingRequest::where('company_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return TeamBuildingRequestResource::collection($requests);
    }

    /**
     * Détail (avec devis). GET /api/v1/team-building-requests/{request}
     */
    public function show(TeamBuildingRequest $teamBuildingRequest): JsonResponse
    {
        Gate::authorize('view', $teamBuildingRequest);

        // Devis + prestataires affectés (avec leur nom commercial) + entreprise.
        // F8.14 — `quotes.booking` : l'écran doit pouvoir proposer « Régler » sur un
        // devis accepté à n'importe quel rechargement, pas seulement au retour du
        // clic. Chargé ici (et pas relu ligne à ligne) pour éviter les N+1.
        $teamBuildingRequest->load(['quotes.booking', 'company', 'providerMissions.provider']);

        return ApiResponse::success(['request' => TeamBuildingRequestResource::make($teamBuildingRequest)]);
    }

    /**
     * File back-office (nouvelles/en étude en tête). GET /api/v1/team-building-requests
     *
     * Réservée aux profils back-office (permission `consulter:dashboard-admin`).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', TeamBuildingRequestStatus::values())],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $requests = TeamBuildingRequest::query()
            ->with('company')
            ->withCount('quotes')
            // Filtre statut (onglet de file) et recherche (référence, ville, entreprise).
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $query->where(function ($sub) use ($term) {
                    $sub->where('reference', 'like', $term)
                        ->orWhere('city', 'like', $term)
                        ->orWhereHas('company', fn ($c) => $c->where('name', 'like', $term));
                });
            })
            ->orderByRaw("FIELD(status, 'nouveau', 'en_etude', 'devis_envoye', 'accepte', 'annule')")
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return TeamBuildingRequestResource::collection($requests);
    }
}
