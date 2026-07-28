<?php

namespace App\Modules\TeamBuilding\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Mobility\Services\CommissionCalculator;
use App\Modules\Pro\Enums\MissionStatus;
use App\Modules\Pro\Http\Resources\ProviderMissionResource;
use App\Modules\Pro\Models\Provider;
use App\Modules\TeamBuilding\Enums\QuoteLineCategory;
use App\Modules\TeamBuilding\Http\Requests\AssignTeamBuildingProviderRequest;
use App\Modules\TeamBuilding\Models\TeamBuildingRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Affectation des prestataires à une demande de team building — F7.2.h.
 *
 * Réalise l'exigence CDC §6 « Team building → affectation prestataires » : le
 * back-office affecte un prestataire VALIDÉ à une brique du pack (lieu,
 * hébergement, restauration, activité, mobilité, animation). Plutôt que de
 * dupliquer la notion, chaque affectation crée une **mission Pro** rattachée à la
 * demande (`team_building_request_id` + `category`) : elle suit alors le cycle de
 * vie standard (le prestataire accepte/refuse puis avance), porte sa commission
 * plateforme figée et remonte dans les revenus du prestataire.
 *
 * Garde : policy `manage` de la demande (back-office, comme la composition de
 * devis).
 */
class TeamBuildingAssignmentController extends Controller
{
    public function __construct(private readonly CommissionCalculator $commissions)
    {
    }

    /**
     * Prestataires affectés à une demande.
     * GET /api/v1/team-building-requests/{request}/assignments
     */
    public function index(TeamBuildingRequest $teamBuildingRequest): AnonymousResourceCollection
    {
        Gate::authorize('manage', $teamBuildingRequest);

        $missions = $teamBuildingRequest->providerMissions()
            ->with('provider')
            ->latest()
            ->get();

        return ProviderMissionResource::collection($missions);
    }

    /**
     * Affecte un prestataire à une brique du pack.
     * POST /api/v1/team-building-requests/{request}/assignments
     */
    public function store(
        AssignTeamBuildingProviderRequest $request,
        TeamBuildingRequest $teamBuildingRequest
    ): JsonResponse {
        Gate::authorize('manage', $teamBuildingRequest);

        $data = $request->validated();
        $provider = Provider::findOrFail($data['provider_id']);

        // On n'affecte qu'à un prestataire validé (cohérent avec le module Pro).
        if (! $provider->isValidated()) {
            throw ValidationException::withMessages([
                'provider_id' => ['Le prestataire doit être validé pour être affecté.'],
            ]);
        }

        $amount = (int) $data['amount_xof'];
        $category = QuoteLineCategory::from($data['category']);
        // Libellé par défaut = « <Catégorie> — <référence demande> ».
        $title = $data['title'] ?? $category->label().' — '.$teamBuildingRequest->reference;

        $mission = $provider->missions()->create([
            'reference' => 'MSN-'.Str::upper(Str::random(8)),
            'team_building_request_id' => $teamBuildingRequest->id,
            'category' => $category->value,
            'client_id' => $teamBuildingRequest->company_id,
            'title' => $title,
            'amount_xof' => $amount,
            'commission_xof' => $this->commissions->commissionFor($amount),
            'scheduled_at' => $data['scheduled_at'] ?? $teamBuildingRequest->start_date,
            'status' => MissionStatus::AFFECTEE->value,
        ]);

        $mission->load('provider');

        return ApiResponse::created(['mission' => ProviderMissionResource::make($mission)]);
    }
}
