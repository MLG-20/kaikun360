<?php

namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Manage\Enums\IncidentStatus;
use App\Modules\Manage\Enums\MandateStatus;
use App\Modules\Manage\Enums\OwnerPayoutStatus;
use App\Modules\Manage\Enums\RentStatus;
use App\Modules\Manage\Http\Resources\MandateResource;
use App\Modules\Manage\Models\Expense;
use App\Modules\Manage\Models\Incident;
use App\Modules\Manage\Models\ManagementMandate;
use App\Modules\Manage\Models\OwnerPayout;
use App\Modules\Manage\Models\Rent;
use App\Modules\Manage\Services\ManagementReportService;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Espace propriétaire — gestion locative (phase B4.5, lecture).
 *
 * Un propriétaire ne voit QUE les données de ses propres biens (scoping par
 * owner_id). Les agents/admins accèdent au détail via la policy.
 */
class ManageController extends Controller
{
    /**
     * Mes mandats, avec leurs agrégats financiers. GET /api/v1/manage/mandates/mine
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        $mandates = $this->withAggregates(
            ManagementMandate::query()->where('owner_id', $request->user()->id)
        )->with(['property.region', 'property.department', 'property.commune', 'property.owner'])
            ->latest()->paginate(15);

        return MandateResource::collection($mandates);
    }

    /**
     * Détail d'un mandat (propriétaire ou agent/admin). GET /api/v1/manage/mandates/{mandate}
     */
    public function show(Request $request, ManagementMandate $mandate): JsonResponse
    {
        // Autorisation via la policy (propriétaire du mandat, ou agent/admin).
        Gate::authorize('view', $mandate);

        $mandate = $this->withAggregates(
            ManagementMandate::query()->whereKey($mandate->id)
        )->with([
            'property.region', 'property.department', 'property.commune', 'property.owner',
            // Lignes détaillées de la fiche (F4.4), les plus récentes d'abord.
            // Bornées pour ne pas alourdir la réponse d'un mandat ancien.
            'rents' => fn ($q) => $q->latest('due_date')->limit(12),
            'payouts' => fn ($q) => $q->latest()->limit(12),
            'incidents' => fn ($q) => $q->latest()->limit(12),
        ])->firstOrFail();

        return ApiResponse::success(['mandate' => MandateResource::make($mandate)]);
    }

    /**
     * Tableau de bord agrégé du propriétaire connecté. GET /api/v1/manage/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $ownerId = $request->user()->id;

        // Mandats du propriétaire (et biens correspondants).
        $mandateIds = ManagementMandate::where('owner_id', $ownerId)->pluck('id');
        $propertyIds = ManagementMandate::where('owner_id', $ownerId)->pluck('property_id');

        return ApiResponse::success([
            'mandats_actifs' => ManagementMandate::where('owner_id', $ownerId)
                ->where('status', MandateStatus::ACTIF->value)->count(),
            'loyers_payes_xof' => (int) Rent::whereIn('mandate_id', $mandateIds)
                ->where('status', RentStatus::PAYE->value)->sum('amount_xof'),
            'loyers_impayes_xof' => (int) Rent::whereIn('mandate_id', $mandateIds)
                ->whereIn('status', [RentStatus::IMPAYE->value, RentStatus::EN_RETARD->value])->sum('amount_xof'),
            'depenses_xof' => (int) Expense::whereIn('property_id', $propertyIds)->sum('amount_xof'),
            'reversements_xof' => (int) OwnerPayout::whereIn('mandate_id', $mandateIds)
                ->where('status', OwnerPayoutStatus::EFFECTUE->value)->sum('amount_xof'),
            'incidents_ouverts' => Incident::whereIn('property_id', $propertyIds)
                ->where('status', IncidentStatus::OUVERT->value)->count(),
        ]);
    }

    /**
     * Rapport mensuel d'un mandat (données structurées). GET .../mandates/{mandate}/report
     *
     * Accessible au propriétaire du mandat comme aux agents/admins (policy `view`).
     * Mois ciblé via `?month=YYYY-MM` (mois courant par défaut).
     */
    public function report(Request $request, ManagementMandate $mandate, ManagementReportService $reports): JsonResponse
    {
        Gate::authorize('view', $mandate);

        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = isset($validated['month'])
            ? CarbonImmutable::createFromFormat('Y-m', $validated['month'])
            : CarbonImmutable::now();

        return ApiResponse::success(['report' => $reports->forMandate($mandate, $month)]);
    }

    /**
     * Ajoute les agrégats (sommes/comptes) à une requête de mandats, sans N+1.
     */
    private function withAggregates(Builder $query): Builder
    {
        return $query
            ->withSum(['rents as loyers_payes' => fn (Builder $q) => $q->where('status', RentStatus::PAYE->value)], 'amount_xof')
            ->withSum(['rents as loyers_impayes' => fn (Builder $q) => $q->whereIn('status', [RentStatus::IMPAYE->value, RentStatus::EN_RETARD->value])], 'amount_xof')
            // Nombre d'échéances (payées / impayées) : deux mois au même loyer
            // affichent le même montant → le compte lève l'ambiguïté à l'écran.
            ->withCount(['rents as loyers_payes_count' => fn (Builder $q) => $q->where('status', RentStatus::PAYE->value)])
            ->withCount(['rents as loyers_impayes_count' => fn (Builder $q) => $q->whereIn('status', [RentStatus::IMPAYE->value, RentStatus::EN_RETARD->value])])
            ->withSum('expenses as depenses_total', 'amount_xof')
            ->withSum(['payouts as reversements_effectues' => fn (Builder $q) => $q->where('status', OwnerPayoutStatus::EFFECTUE->value)], 'amount_xof')
            ->withCount(['incidents as incidents_ouverts' => fn (Builder $q) => $q->where('status', IncidentStatus::OUVERT->value)]);
    }
}
