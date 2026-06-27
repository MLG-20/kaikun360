<?php

namespace App\Modules\Build\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Modules\Build\Http\Requests\StoreReportRequest;
use App\Modules\Build\Http\Resources\ReportResource;
use App\Modules\Build\Models\ConstructionRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Publication des rapports de suivi de chantier par les AGENTS (phase B5.5).
 *
 * Réservé à la permission `gerer:chantiers` (middleware `can:` sur la route).
 * Le rapport est rattaché polymorphiquement à la demande et l'auteur est tracé.
 */
class ConstructionReportController extends Controller
{
    /**
     * Publie un rapport sur une demande. POST /api/v1/construction-requests/{constructionRequest}/reports
     */
    public function store(StoreReportRequest $request, ConstructionRequest $constructionRequest): JsonResponse
    {
        /** @var Report $report */
        $report = $constructionRequest->reports()->create($request->validated() + [
            'reference' => 'RPT-'.Str::upper(Str::random(8)),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(['report' => ReportResource::make($report)]);
    }
}
