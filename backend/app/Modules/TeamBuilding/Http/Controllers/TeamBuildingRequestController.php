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

        $teamBuildingRequest->load('quotes');

        return ApiResponse::success(['request' => TeamBuildingRequestResource::make($teamBuildingRequest)]);
    }

    /**
     * File back-office (nouvelles/en étude en tête). GET /api/v1/team-building-requests
     *
     * Réservée aux profils back-office (permission `consulter:dashboard-admin`).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $requests = TeamBuildingRequest::query()
            ->withCount('quotes')
            ->orderByRaw("FIELD(status, 'nouveau', 'en_etude', 'devis_envoye', 'accepte', 'annule')")
            ->latest()
            ->paginate(20);

        return TeamBuildingRequestResource::collection($requests);
    }
}
