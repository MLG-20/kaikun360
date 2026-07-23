<?php

use App\Modules\Pro\Http\Controllers\ProviderAvailabilityController;
use App\Modules\Pro\Http\Controllers\ProviderMissionController;
use App\Modules\Pro\Http\Controllers\ProviderRegistrationController;
use App\Modules\Pro\Http\Controllers\ProviderValidationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes du module Pro (marketplace prestataires)
|--------------------------------------------------------------------------
|
| Inscription/gestion par le prestataire ; validation & charte qualité réservées
| aux agents (permission `valider:prestataire`). Les missions arrivent en B10.3.
|
*/

// --- Espace prestataire (auth) -----------------------------------------------
Route::middleware('auth:sanctum')->prefix('providers')->group(function () {
    Route::post('/', [ProviderRegistrationController::class, 'store'])->middleware('verified.account');
    Route::get('/mine', [ProviderRegistrationController::class, 'mine']);

    // Disponibilités du prestataire (F5.4) : planning hebdomadaire + indispos.
    // Déclarées AVANT `/{provider}/missions` : `availability` n'est pas numérique
    // donc aucune collision, mais on garde les routes « dispo » groupées ici.
    Route::get('/availability', [ProviderAvailabilityController::class, 'show']);
    Route::put('/availability/weekly', [ProviderAvailabilityController::class, 'updateWeekly']);
    Route::post('/availability/unavailability', [ProviderAvailabilityController::class, 'storeUnavailability']);
    Route::delete('/availability/unavailability/{unavailability}', [ProviderAvailabilityController::class, 'destroyUnavailability'])
        ->whereNumber('unavailability');

    // Affectation d'une mission (policy assignMission = admin).
    Route::post('/{provider}/missions', [ProviderMissionController::class, 'store'])->whereNumber('provider');
});

// --- Missions côté prestataire (auth) ----------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::get('provider-missions/mine', [ProviderMissionController::class, 'mine']);
    // Synthèse revenus & commissions (F5.3) — agrégat scopé au prestataire.
    Route::get('provider-missions/earnings', [ProviderMissionController::class, 'earnings']);
    Route::patch('provider-missions/{mission}/{action}', [ProviderMissionController::class, 'transition'])
        ->whereNumber('mission')
        ->whereIn('action', ['accept', 'refuse', 'start', 'complete']);
});

// --- Validation & charte qualité (permission valider:prestataire) ------------
Route::middleware(['auth:sanctum', 'can:valider:prestataire'])->prefix('providers')->group(function () {
    Route::patch('/{provider}/validate', [ProviderValidationController::class, 'validate'])->whereNumber('provider');
    Route::patch('/{provider}/reject', [ProviderValidationController::class, 'reject'])->whereNumber('provider');
    Route::patch('/{provider}/suspend', [ProviderValidationController::class, 'suspend'])->whereNumber('provider');
    Route::patch('/{provider}/warn', [ProviderValidationController::class, 'warn'])->whereNumber('provider');
});
