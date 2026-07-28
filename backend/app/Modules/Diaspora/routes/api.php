<?php

use App\Modules\Diaspora\Http\Controllers\DiasporaAssignmentController;
use App\Modules\Diaspora\Http\Controllers\DiasporaProjectController;
use App\Modules\Diaspora\Http\Controllers\DiasporaReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes du module Diaspora (projets pilotés à distance)
|--------------------------------------------------------------------------
|
| Le client dépose et suit ses projets ; l'agent affecté y accède en
| lecture/écriture (policy). L'affectation et la vue priorisée relèvent du
| back-office (admin / permission consulter:dashboard-admin).
|
*/

Route::middleware('auth:sanctum')->prefix('diaspora-projects')->group(function () {
    // Client.
    Route::post('/', [DiasporaProjectController::class, 'store']);
    Route::get('/mine', [DiasporaProjectController::class, 'mine']);

    // Vue back-office priorisée (agents/admins).
    Route::get('/', [DiasporaProjectController::class, 'index'])->middleware('can:consulter:dashboard-admin');

    // Détail + rapports (policy view/update côté contrôleur).
    Route::get('/{project}', [DiasporaProjectController::class, 'show'])->whereNumber('project');
    // Pilotage back-office : statut et/ou priorité (policy update = agent affecté ou admin).
    Route::patch('/{project}', [DiasporaProjectController::class, 'update'])->whereNumber('project');
    Route::get('/{project}/reports', [DiasporaReportController::class, 'index'])->whereNumber('project');
    Route::post('/{project}/reports', [DiasporaReportController::class, 'store'])->whereNumber('project');

    // Affectation d'un agent (back-office, policy assign = admin).
    Route::patch('/{project}/assign', [DiasporaAssignmentController::class, 'assign'])->whereNumber('project');
});
