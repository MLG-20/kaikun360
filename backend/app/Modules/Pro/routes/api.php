<?php

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
    Route::post('/', [ProviderRegistrationController::class, 'store']);
    Route::get('/mine', [ProviderRegistrationController::class, 'mine']);
});

// --- Validation & charte qualité (permission valider:prestataire) ------------
Route::middleware(['auth:sanctum', 'can:valider:prestataire'])->prefix('providers')->group(function () {
    Route::patch('/{provider}/validate', [ProviderValidationController::class, 'validate'])->whereNumber('provider');
    Route::patch('/{provider}/reject', [ProviderValidationController::class, 'reject'])->whereNumber('provider');
    Route::patch('/{provider}/suspend', [ProviderValidationController::class, 'suspend'])->whereNumber('provider');
    Route::patch('/{provider}/warn', [ProviderValidationController::class, 'warn'])->whereNumber('provider');
});
