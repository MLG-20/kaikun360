<?php

namespace App\Modules\Diaspora\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Diaspora\Enums\DiasporaPriority;
use App\Modules\Diaspora\Enums\DiasporaProjectStatus;
use App\Modules\Diaspora\Http\Requests\StoreDiasporaProjectRequest;
use App\Modules\Diaspora\Http\Requests\UpdateDiasporaProjectRequest;
use App\Modules\Diaspora\Http\Resources\DiasporaProjectResource;
use App\Modules\Diaspora\Models\DiasporaProject;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Projets diaspora — dépôt par le client, suivi et vue back-office (phase B8.2).
 */
class DiasporaProjectController extends Controller
{
    /**
     * Dépose un projet diaspora. POST /api/v1/diaspora-projects
     */
    public function store(StoreDiasporaProjectRequest $request): JsonResponse
    {
        $project = DiasporaProject::create($request->validated() + [
            'reference' => 'DSP-'.Str::upper(Str::random(8)),
            'client_id' => $request->user()->id,
            'status' => DiasporaProjectStatus::NOUVEAU->value,
        ]);

        return ApiResponse::created(['project' => DiasporaProjectResource::make($project)]);
    }

    /**
     * Mes projets diaspora. GET /api/v1/diaspora-projects/mine
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        $projects = DiasporaProject::where('client_id', $request->user()->id)
            ->withCount('reports')
            ->latest()
            ->paginate(15);

        return DiasporaProjectResource::collection($projects);
    }

    /**
     * Détail d'un projet. GET /api/v1/diaspora-projects/{project}
     */
    public function show(DiasporaProject $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $project->loadCount('reports');
        $project->load(['client', 'agent']);

        return ApiResponse::success(['project' => DiasporaProjectResource::make($project)]);
    }

    /**
     * Pilotage back-office d'un dossier : statut et/ou priorité.
     * PATCH /api/v1/diaspora-projects/{project}
     *
     * Contrairement à l'affectation d'un agent (`/assign`, qui bascule le dossier
     * « en cours »), cette mise à jour est **sans effet de bord** : elle permet de
     * (re)prioriser un dossier avant même toute affectation et de faire progresser
     * son statut (clôturer/annuler). Écriture réservée à l'agent affecté ou à un
     * admin (policy `update`, qui mentionne explicitement le statut).
     */
    public function update(UpdateDiasporaProjectRequest $request, DiasporaProject $project): JsonResponse
    {
        Gate::authorize('update', $project);

        $project->update($request->validated());
        $project->loadCount('reports');
        $project->load(['client', 'agent']);

        return ApiResponse::success(['project' => DiasporaProjectResource::make($project)]);
    }

    /**
     * Vue back-office : tous les dossiers, priorisés. GET /api/v1/diaspora-projects
     *
     * Réservée aux profils back-office (permission `consulter:dashboard-admin`).
     * Les dossiers à forte valeur (priorité stratégique/haute) remontent en tête.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', DiasporaProjectStatus::values())],
            'priority' => ['nullable', 'string', 'in:'.implode(',', DiasporaPriority::values())],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $projects = DiasporaProject::query()
            ->with(['client', 'agent'])
            ->withCount('reports')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')->toString()))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $query->where(function ($sub) use ($term) {
                    $sub->where('reference', 'like', $term)
                        ->orWhere('residence_country', 'like', $term)
                        ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $term));
                });
            })
            // Priorité décroissante (stratégique > haute > normale), puis récence.
            ->orderByRaw("FIELD(priority, 'strategique', 'haute', 'normale')")
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return DiasporaProjectResource::collection($projects);
    }
}
