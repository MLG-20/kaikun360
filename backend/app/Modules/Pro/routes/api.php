<?php

use App\Modules\Pro\Http\Controllers\ProviderAvailabilityController;
use App\Modules\Pro\Http\Controllers\ProviderMissionController;
use App\Modules\Pro\Http\Controllers\ProviderProfileController;
use App\Modules\Pro\Http\Controllers\ProviderRegistrationController;
use App\Modules\Pro\Http\Controllers\ProviderReviewController;
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

    // « Mes services » (F5) : édition du profil + gestion des certifications par
    // le prestataire. Segments non numériques (`mine`, `certifications`) → aucune
    // collision avec `/{provider}/...` (whereNumber).
    Route::put('/mine', [ProviderProfileController::class, 'update']);
    Route::post('/certifications', [ProviderProfileController::class, 'storeCertification']);
    Route::delete('/certifications/{certification}', [ProviderProfileController::class, 'destroyCertification'])
        ->whereNumber('certification');
});

// Téléchargement d'un justificatif de certification (F8.0) : hors du groupe
// `auth:sanctum`, l'accès est prouvé par la SIGNATURE de l'URL (valable 10 min,
// produite par ProviderCertificationResource). Le nom de route doit rester
// identique à celui utilisé par la ressource.
Route::get('providers/certifications/{certification}/download', [ProviderProfileController::class, 'downloadCertification'])
    ->name('providers.certifications.download')
    ->whereNumber('certification')
    ->middleware('signed');

Route::middleware('auth:sanctum')->prefix('providers')->group(function () {

    // Disponibilités du prestataire (F5.4) : planning hebdomadaire + indispos.
    // Déclarées AVANT `/{provider}/missions` : `availability` n'est pas numérique
    // donc aucune collision, mais on garde les routes « dispo » groupées ici.
    Route::get('/availability', [ProviderAvailabilityController::class, 'show']);
    Route::put('/availability/weekly', [ProviderAvailabilityController::class, 'updateWeekly']);
    Route::post('/availability/unavailability', [ProviderAvailabilityController::class, 'storeUnavailability']);
    Route::delete('/availability/unavailability/{unavailability}', [ProviderAvailabilityController::class, 'destroyUnavailability'])
        ->whereNumber('unavailability');

    // Avis reçus (F5.5) : consultation des avis publiés qui concernent le
    // prestataire connecté (ressources + avis directs). Segment non numérique
    // `reviews` → aucune collision avec `/{provider}/...` (whereNumber).
    Route::get('/reviews', [ProviderReviewController::class, 'index']);

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
