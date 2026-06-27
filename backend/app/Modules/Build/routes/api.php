<?php

use App\Modules\Build\Http\Controllers\ConstructionReportController;
use App\Modules\Build\Http\Controllers\ConstructionRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes du module Build (construction / rénovation / devis)
|--------------------------------------------------------------------------
|
| Espace client (auth) : dépôt et suivi de demandes de construction, simulation
| de budget. La publication des rapports de chantier est réservée aux agents
| (permission `gerer:chantiers`). Le scoping client est assuré par la policy.
|
*/

// --- Espace client & lecture (auth) ------------------------------------------
Route::middleware('auth:sanctum')->prefix('construction-requests')->group(function () {
    Route::post('/', [ConstructionRequestController::class, 'store']);
    Route::post('/simulate', [ConstructionRequestController::class, 'simulate']);
    Route::get('/mine', [ConstructionRequestController::class, 'mine']);
    Route::get('/{constructionRequest}', [ConstructionRequestController::class, 'show'])->whereNumber('constructionRequest');
    Route::get('/{constructionRequest}/reports', [ConstructionRequestController::class, 'reports'])->whereNumber('constructionRequest');
});

// --- Suivi de chantier par les agents (permission gerer:chantiers) ------------
Route::middleware(['auth:sanctum', 'can:gerer:chantiers'])->prefix('construction-requests')->group(function () {
    Route::post('/{constructionRequest}/reports', [ConstructionReportController::class, 'store'])->whereNumber('constructionRequest');
});
