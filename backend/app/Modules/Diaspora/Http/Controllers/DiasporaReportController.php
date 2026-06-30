<?php

namespace App\Modules\Diaspora\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Modules\Build\Http\Resources\ReportResource;
use App\Modules\Diaspora\Http\Requests\StoreDiasporaReportRequest;
use App\Modules\Diaspora\Models\DiasporaProject;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Rapports de suivi d'un projet diaspora (phase B8.2).
 *
 * Lecture : client propriétaire, agent affecté ou admin (policy `view`).
 * Ajout : agent affecté ou admin (policy `update`). Réutilise le modèle
 * transversal `Report` et `ReportResource` (partagés avec Build).
 */
class DiasporaReportController extends Controller
{
    /**
     * Liste des rapports. GET /api/v1/diaspora-projects/{project}/reports
     */
    public function index(DiasporaProject $project): AnonymousResourceCollection
    {
        Gate::authorize('view', $project);

        $reports = $project->reports()->latest('reported_at')->paginate(15);

        return ReportResource::collection($reports);
    }

    /**
     * Ajoute un rapport. POST /api/v1/diaspora-projects/{project}/reports
     */
    public function store(StoreDiasporaReportRequest $request, DiasporaProject $project): JsonResponse
    {
        Gate::authorize('update', $project);

        /** @var Report $report */
        $report = $project->reports()->create($request->validated() + [
            'reference' => 'RPT-'.Str::upper(Str::random(8)),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(['report' => ReportResource::make($report)]);
    }
}
