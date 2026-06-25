<?php

use App\Modules\Core\Http\Controllers\AuthController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes du module Core
|--------------------------------------------------------------------------
|
| Routes transverses de l'API (santé, version, etc.).
| Elles héritent automatiquement du préfixe "api/v1" et du middleware
| "throttle:api" définis globalement — inutile de les redéclarer ici.
|
*/

// GET /api/v1/version
// Endpoint public de "liveness" : permet de vérifier que l'API répond
// et de connaître sa version. Il sert aussi de test concret de l'enveloppe
// JSON standard (ApiResponse::success) mise en place en phase B0.4.
Route::get('/version', function () {
    return ApiResponse::success([
        'name'   => config('app.name'),
        'api'    => 'v1',
        'status' => 'ok',
    ]);
});

/*
| Authentification (phase B1.3).
| register/login sont publics ; logout exige un token Sanctum valide.
*/
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});
