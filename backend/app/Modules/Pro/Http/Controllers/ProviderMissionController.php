<?php

namespace App\Modules\Pro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Billing\CommissionCalculator;
use App\Modules\Pro\Enums\MissionStatus;
use App\Modules\Pro\Http\Requests\AssignMissionRequest;
use App\Modules\Pro\Http\Resources\ProviderMissionResource;
use App\Modules\Pro\Models\Provider;
use App\Modules\Pro\Models\ProviderMission;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Missions affectées aux prestataires (phase B10.3).
 *
 * Affectation par le back-office (admin) — réservée aux prestataires VALIDÉS ; la
 * commission plateforme est figée via `CommissionCalculator` (réutilisé, B7). Le
 * prestataire accepte/refuse puis fait progresser sa mission.
 */
class ProviderMissionController extends Controller
{
    public function __construct(private readonly CommissionCalculator $commissions)
    {
    }

    /**
     * Affecte une mission à un prestataire. POST /api/v1/providers/{provider}/missions
     */
    public function store(AssignMissionRequest $request, Provider $provider): JsonResponse
    {
        Gate::authorize('assignMission', $provider);

        // On n'affecte de mission qu'à un prestataire validé.
        if (! $provider->isValidated()) {
            throw ValidationException::withMessages([
                'provider' => ['Le prestataire doit être validé pour recevoir une mission.'],
            ]);
        }

        $data = $request->validated();
        $amount = (int) $data['amount_xof'];

        $mission = $provider->missions()->create([
            'reference' => 'MSN-'.Str::upper(Str::random(8)),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'amount_xof' => $amount,
            'commission_xof' => $this->commissions->commissionFor($amount),
            'client_id' => $data['client_id'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'status' => MissionStatus::AFFECTEE->value,
        ]);

        return ApiResponse::created(['mission' => ProviderMissionResource::make($mission)]);
    }

    /**
     * Mes missions (côté prestataire). GET /api/v1/provider-missions/mine
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        $missions = ProviderMission::whereHas('provider', fn ($q) => $q->where('user_id', $request->user()->id))
            ->latest()
            ->paginate(15);

        return ProviderMissionResource::collection($missions);
    }

    /**
     * Synthèse revenus & commissions du prestataire. GET /api/v1/provider-missions/earnings
     *
     * Agrège les missions du prestataire connecté (scopé `user_id`) en trois
     * blocs : **réalisé** (missions terminées → argent gagné), **à venir**
     * (missions acceptées ou en cours → engagé mais pas encore encaissé) et le
     * nombre de missions **à traiter** (affectées, en attente de réponse). Le net
     * est toujours le montant moins la commission Kaikun.
     */
    public function earnings(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $mine = fn () => ProviderMission::whereHas('provider', fn ($q) => $q->where('user_id', $userId));

        // Réalisé = missions terminées.
        $revenuRealise = (int) $mine()->where('status', MissionStatus::TERMINEE->value)->sum('amount_xof');
        $commissionRealisee = (int) $mine()->where('status', MissionStatus::TERMINEE->value)->sum('commission_xof');

        // À venir = missions acceptées + en cours (engagées, pas encore encaissées).
        $enCoursStatuts = [MissionStatus::ACCEPTEE->value, MissionStatus::EN_COURS->value];
        $revenuAVenir = (int) $mine()->whereIn('status', $enCoursStatuts)->sum('amount_xof');
        $commissionAVenir = (int) $mine()->whereIn('status', $enCoursStatuts)->sum('commission_xof');

        return ApiResponse::success([
            'revenu_realise_xof' => $revenuRealise,
            'commission_realisee_xof' => $commissionRealisee,
            'net_realise_xof' => $revenuRealise - $commissionRealisee,
            'missions_terminees' => $mine()->where('status', MissionStatus::TERMINEE->value)->count(),
            'revenu_a_venir_xof' => $revenuAVenir,
            'net_a_venir_xof' => $revenuAVenir - $commissionAVenir,
            'missions_a_venir' => $mine()->whereIn('status', $enCoursStatuts)->count(),
            'missions_a_traiter' => $mine()->where('status', MissionStatus::AFFECTEE->value)->count(),
        ]);
    }

    /**
     * Fait progresser le statut d'une mission (par le prestataire).
     * PATCH /api/v1/provider-missions/{mission}/{action}
     */
    public function transition(Request $request, ProviderMission $mission, string $action): JsonResponse
    {
        // Seul le prestataire affecté agit sur sa mission.
        if ($mission->provider->user_id !== $request->user()->id) {
            abort(403);
        }

        $target = $this->resolveTransition($mission->status, $action);

        $mission->update(['status' => $target->value]);

        return ApiResponse::success(['mission' => ProviderMissionResource::make($mission->fresh())]);
    }

    /**
     * Valide la transition demandée depuis le statut courant.
     */
    private function resolveTransition(MissionStatus $current, string $action): MissionStatus
    {
        $allowed = match ($action) {
            'accept' => [MissionStatus::AFFECTEE, MissionStatus::ACCEPTEE],
            'refuse' => [MissionStatus::AFFECTEE, MissionStatus::REFUSEE],
            'start' => [MissionStatus::ACCEPTEE, MissionStatus::EN_COURS],
            'complete' => [MissionStatus::EN_COURS, MissionStatus::TERMINEE],
            default => null,
        };

        if ($allowed === null || $current !== $allowed[0]) {
            throw ValidationException::withMessages([
                'status' => ["Transition « {$action} » impossible depuis le statut « {$current->value} »."],
            ]);
        }

        return $allowed[1];
    }
}
