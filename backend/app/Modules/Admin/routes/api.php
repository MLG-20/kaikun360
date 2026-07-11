<?php

use App\Modules\Admin\Http\Controllers\AdminDashboardController;
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
});
