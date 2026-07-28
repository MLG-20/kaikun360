<?php

use App\Modules\TeamBuilding\Http\Controllers\TeamBuildingAssignmentController;
use App\Modules\TeamBuilding\Http\Controllers\TeamBuildingQuoteController;
use App\Modules\TeamBuilding\Http\Controllers\TeamBuildingRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes du module Team Building (packs groupe entreprise)
|--------------------------------------------------------------------------
|
| L'entreprise dépose/suit ses demandes et accepte les devis ; la composition
| et l'envoi des devis relèvent du back-office (policy admin). La vue file
| d'attente est réservée à `consulter:dashboard-admin`.
|
*/

Route::middleware('auth:sanctum')->group(function () {
    // Entreprise.
    Route::post('team-building-requests', [TeamBuildingRequestController::class, 'store']);
    Route::get('team-building-requests/mine', [TeamBuildingRequestController::class, 'mine']);

    // File back-office.
    Route::get('team-building-requests', [TeamBuildingRequestController::class, 'index'])
        ->middleware('can:consulter:dashboard-admin');

    // Détail + devis (policy view/manage côté contrôleur).
    Route::get('team-building-requests/{teamBuildingRequest}', [TeamBuildingRequestController::class, 'show'])->whereNumber('teamBuildingRequest');
    Route::get('team-building-requests/{teamBuildingRequest}/quotes', [TeamBuildingQuoteController::class, 'index'])->whereNumber('teamBuildingRequest');
    Route::post('team-building-requests/{teamBuildingRequest}/quotes', [TeamBuildingQuoteController::class, 'compose'])->whereNumber('teamBuildingRequest');

    // Cycle de vie d'un devis.
    Route::patch('team-building-quotes/{quote}/send', [TeamBuildingQuoteController::class, 'send'])->whereNumber('quote');
    Route::patch('team-building-quotes/{quote}/accept', [TeamBuildingQuoteController::class, 'accept'])->whereNumber('quote');

    // Affectation des prestataires (back-office, F7.2.h — policy `manage`).
    Route::get('team-building-requests/{teamBuildingRequest}/assignments', [TeamBuildingAssignmentController::class, 'index'])->whereNumber('teamBuildingRequest');
    Route::post('team-building-requests/{teamBuildingRequest}/assignments', [TeamBuildingAssignmentController::class, 'store'])->whereNumber('teamBuildingRequest');
});
