<?php

namespace App\Modules\Build\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Build\Enums\ConstructionLot;
use App\Modules\Build\Http\Requests\AssignConstructionProviderRequest;
use App\Modules\Build\Models\ConstructionRequest;
use App\Modules\Mobility\Services\CommissionCalculator;
use App\Modules\Pro\Enums\MissionStatus;
use App\Modules\Pro\Http\Resources\ProviderMissionResource;
use App\Modules\Pro\Models\Provider;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Affectation des prestataires BTP à un chantier — F7.3.e3.
 *
 * Réalise la dernière exigence non couverte du CDC §6 *Construction* :
 * « prestataires BTP ». Même parti pris qu'en F7.2.h pour le team building — on ne
 * duplique pas la notion d'affectation, chaque affectation crée une **mission Pro**
 * rattachée au chantier (`construction_request_id` + `category` = le lot) : elle
 * suit le cycle de vie standard (le prestataire accepte/refuse puis avance), porte
 * sa commission plateforme figée et remonte dans ses revenus.
 *
 * On affecte **par corps d'état**, pas au chantier en bloc : un chantier fait
 * intervenir un maçon, un électricien et un plombier, chacun sur son lot, chacun
 * avec son montant et sa commission.
 *
 * Garde : `gerer:chantiers` (middleware `can:` sur les routes), comme le reste du
 * suivi de chantier. ⚠️ Volontairement PLUS OUVERT que le team building, dont les
 * policies exigent le rôle admin et bloquent l'agent en 403 — le CDC §7 confie
 * pourtant l'« affectation prestataire » à l'agent. Ici la garde par permission
 * respecte le cahier des charges ; l'écart du team building reste à trancher.
 */
class ConstructionAssignmentController extends Controller
{
    public function __construct(private readonly CommissionCalculator $commissions)
    {
    }

    /**
     * Prestataires affectés au chantier.
     * GET /api/v1/construction-requests/{constructionRequest}/assignments
     */
    public function index(ConstructionRequest $constructionRequest): AnonymousResourceCollection
    {
        Gate::authorize('view', $constructionRequest);

        return ProviderMissionResource::collection(
            $constructionRequest->providerMissions()->with('provider')->get()
        );
    }

    /**
     * Affecte un prestataire validé à un lot du chantier.
     * POST /api/v1/construction-requests/{constructionRequest}/assignments
     */
    public function store(
        AssignConstructionProviderRequest $request,
        ConstructionRequest $constructionRequest
    ): JsonResponse {
        $data = $request->validated();
        $provider = Provider::findOrFail($data['provider_id']);

        // Cohérent avec le module Pro et le team building : on n'envoie sur un
        // chantier qu'un prestataire dont le dossier a été validé.
        if (! $provider->isValidated()) {
            throw ValidationException::withMessages([
                'provider_id' => ['Le prestataire doit être validé pour être affecté.'],
            ]);
        }

        $lot = ConstructionLot::from($data['lot']);
        $amount = (int) $data['amount_xof'];

        $mission = $provider->missions()->create([
            'reference' => 'MSN-'.Str::upper(Str::random(8)),
            'construction_request_id' => $constructionRequest->id,
            'category' => $lot->value,
            'client_id' => $constructionRequest->client_id,
            // Libellé par défaut = « <Lot> — <référence du dossier> ».
            'title' => $data['title'] ?? $lot->label().' — '.$constructionRequest->reference,
            'amount_xof' => $amount,
            'commission_xof' => $this->commissions->commissionFor($amount),
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'status' => MissionStatus::AFFECTEE->value,
        ]);

        $mission->load('provider');

        return ApiResponse::created(['mission' => ProviderMissionResource::make($mission)]);
    }
}
