<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Http\Resources\QuoteResource;
use App\Models\ServiceRequest;
use App\Modules\Admin\Http\Resources\AdminServiceRequestResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Activitylog\Models\Activity;

/**
 * File de traitement des demandes clients — back-office (F8.9).
 *
 * ⚠️ **Ce contrôleur comble un trou, pas un confort.** Depuis B11.2, une
 * demande déposée depuis le site public déclenchait bien une alerte interne
 * (« Nouvelle demande à traiter ») et pouvait changer de statut
 * (`PATCH /requests/{id}/status`, garde `traiter:demandes`) — mais **aucune
 * route ne permettait à l'équipe de LISTER ces demandes**. L'agent recevait
 * donc un e-mail l'invitant à traiter un dossier qu'il n'avait aucun moyen de
 * retrouver, et `GET /requests/{id}` répondait 403 à tout autre que le
 * demandeur. Le cahier des charges §7 confie pourtant explicitement le
 * « traitement demandes » à l'agent Kaikun.
 *
 * Les deux routes ci-dessous sont donc **en lecture** ; le pilotage réel (le
 * changement de statut, la proposition d'un devis) reste où il vivait déjà,
 * dans `RequestController` et `QuoteController`, sous la même permission. On
 * ne duplique pas une machine à états.
 */
class AdminRequestController extends Controller
{
    /**
     * File de traitement. GET /api/v1/admin/requests
     *
     * Filtres : `status`, `service_type`, `priority`, `search` (référence, ville
     * ou identité du demandeur), `per_page`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $requests = ServiceRequest::query()
            // Le demandeur remonte dès la LISTE : sans lui, une file de
            // traitement ne dit pas de qui vient le dossier (et le charger
            // ligne à ligne serait un N+1).
            ->with('user')
            ->withCount('quotes')
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->toString()),
            )
            ->when(
                $request->filled('service_type'),
                fn ($query) => $query->where('service_type', $request->string('service_type')->toString()),
            )
            ->when(
                $request->filled('priority'),
                fn ($query) => $query->where('priority', $request->string('priority')->toString()),
            )
            // Recherche libre : la référence est ce qu'un client cite au
            // téléphone, mais on cherche aussi par identité — un client rappelle
            // rarement avec son numéro de dossier sous les yeux.
            ->when($request->filled('search'), function ($query) use ($request) {
                $terme = '%'.$request->string('search')->toString().'%';

                $query->where(function ($sous) use ($terme) {
                    $sous->where('reference', 'like', $terme)
                        ->orWhere('city', 'like', $terme)
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $terme)
                            ->orWhere('email', 'like', $terme)
                            ->orWhere('phone', 'like', $terme));
                });
            })
            // Les urgences d'abord, puis les plus anciennes : dans une file de
            // traitement, c'est le dossier qui attend depuis le plus longtemps
            // qui coûte le plus cher, pas le dernier arrivé.
            // (Un `CASE` plutôt que le `FIELD()` de MySQL : la suite de tests
            // doit pouvoir tourner sur SQLite.)
            ->orderByRaw("CASE priority WHEN 'urgente' THEN 0 WHEN 'haute' THEN 1 ELSE 2 END")
            ->oldest()
            ->paginate(max(1, min(100, (int) $request->integer('per_page', 20))));

        return AdminServiceRequestResource::collection($requests);
    }

    /**
     * Fiche d'une demande. GET /api/v1/admin/requests/{serviceRequest}
     *
     * Le pendant staff de `GET /requests/{id}`, qui est réservé au demandeur.
     * Rassemble ce qu'il faut pour décider : le dossier, son auteur joignable,
     * les devis déjà proposés et l'historique des décisions prises.
     */
    public function show(ServiceRequest $serviceRequest): JsonResponse
    {
        $serviceRequest->load('user')->loadCount('quotes');

        // Historique : les changements de statut sont journalisés par
        // `RequestController::updateStatus`. C'est ce qui rend une file
        // auditable — qui a fait avancer le dossier, et quand.
        $historique = Activity::query()
            ->where('subject_type', $serviceRequest->getMorphClass())
            ->where('subject_id', $serviceRequest->id)
            ->with('causer')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (Activity $entree) => [
                'id' => $entree->id,
                'description' => $entree->description,
                'log_name' => $entree->log_name,
                'causer_name' => $entree->causer?->name,
                'properties' => $entree->properties,
                'created_at' => $entree->created_at,
            ]);

        return ApiResponse::success([
            'request' => AdminServiceRequestResource::make($serviceRequest),
            'quotes' => QuoteResource::collection($serviceRequest->quotes()->latest()->get()),
            'activity' => $historique,
        ]);
    }

    /**
     * Référentiels de filtrage. GET /api/v1/admin/requests/filters
     *
     * Les libellés vivent dans les enums PHP : les recopier dans le frontend
     * ferait diverger les deux le jour où une étape est ajoutée.
     */
    public function filters(): JsonResponse
    {
        $lister = fn (array $cases) => collect($cases)
            ->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()])
            ->all();

        return ApiResponse::success([
            'statuses' => $lister(RequestStatus::cases()),
            'service_types' => $lister(ServiceType::cases()),
            'priorities' => $lister(RequestPriority::cases()),
        ]);
    }
}
