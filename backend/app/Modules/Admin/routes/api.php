<?php

use App\Modules\Admin\Http\Controllers\AdminDashboardController;
use App\Modules\Admin\Http\Controllers\AdminUserController;
use App\Modules\Admin\Http\Controllers\ValidationQueueController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes du module Admin (back-office)
|--------------------------------------------------------------------------
|
| Endpoints transversaux de pilotage réservés aux profils back-office
| (agents / administrateurs). Le préfixe commun est "admin". Chaque route
| exige au minimum la permission `consulter:dashboard-admin` ; les actions
| sensibles (gestion des utilisateurs, paramétrage) exigeront des permissions
| plus fines dans les sous-phases suivantes (B13.3+).
|
*/

Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    // B13.1 — Tableau de bord : indicateurs de pilotage agrégés.
    Route::get('/dashboard', [AdminDashboardController::class, 'show'])
        ->middleware('can:consulter:dashboard-admin');

    // B13.2 — File de validation générique + décision par type de ressource.
    // L'accès back-office est gardé par `consulter:dashboard-admin` ; la
    // permission fine (valider:bien, valider:vehicule…) est vérifiée dans le
    // contrôleur selon le {type} ciblé.
    Route::get('/queue', [ValidationQueueController::class, 'index'])
        ->middleware('can:consulter:dashboard-admin');

    Route::patch('/validate/{type}/{id}', [ValidationQueueController::class, 'decide'])
        ->where('type', '[a-z]+')
        ->whereNumber('id')
        ->middleware('can:consulter:dashboard-admin');

    // B13.3 — Gestion des comptes (rôles, statut, désactivation). Niveau admin.
    Route::get('/users', [AdminUserController::class, 'index'])
        ->middleware('can:gerer:utilisateurs');

    Route::patch('/users/{user}', [AdminUserController::class, 'update'])
        ->whereNumber('user')
        ->middleware('can:gerer:utilisateurs');
});
