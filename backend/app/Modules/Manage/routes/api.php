<?php

use App\Modules\Manage\Http\Controllers\ManageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes du module Manage (gestion locative)
|--------------------------------------------------------------------------
|
| Espace propriétaire (lecture) — phase B4.5. Toutes les routes exigent une
| authentification ; le scoping par propriétaire est assuré côté contrôleur/policy.
|
*/

Route::middleware('auth:sanctum')->prefix('manage')->group(function () {
    Route::get('/dashboard', [ManageController::class, 'dashboard']);
    Route::get('/mandates/mine', [ManageController::class, 'mine']);
    Route::get('/mandates/{mandate}', [ManageController::class, 'show'])->whereNumber('mandate');
});
