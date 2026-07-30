<?php

use App\Modules\Build\Http\Controllers\ConstructionAssignmentController;
use App\Modules\Build\Http\Controllers\ConstructionMilestoneController;
use App\Modules\Build\Http\Controllers\ConstructionQuoteController;
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

// --- Simulateur de budget PUBLIC (pur calcul, aucune donnée perso) -----------
// La page Construction du site est accessible sans compte : elle doit pouvoir
// chiffrer un projet en direct via ce endpoint, qui reflète le barème géré au
// back-office (réglage `build.pricing`).
Route::post('construction-requests/simulate', [ConstructionRequestController::class, 'simulate']);

// --- Espace client & lecture (auth) ------------------------------------------
Route::middleware('auth:sanctum')->prefix('construction-requests')->group(function () {
    Route::post('/', [ConstructionRequestController::class, 'store']);
    Route::get('/mine', [ConstructionRequestController::class, 'mine']);
    Route::get('/{constructionRequest}', [ConstructionRequestController::class, 'show'])->whereNumber('constructionRequest');
    Route::get('/{constructionRequest}/reports', [ConstructionRequestController::class, 'reports'])->whereNumber('constructionRequest');

    // Devis du chantier (F7.3.e2) : lecture ouverte au client comme à l'équipe
    // (policy `view`) — le client doit pouvoir relire ce qu'on lui a envoyé.
    Route::get('/{constructionRequest}/quotes', [ConstructionQuoteController::class, 'index'])->whereNumber('constructionRequest');

    // Prestataires BTP affectés (F7.3.e3) : lecture ouverte au client aussi — il a
    // le droit de savoir qui intervient chez lui.
    Route::get('/{constructionRequest}/assignments', [ConstructionAssignmentController::class, 'index'])->whereNumber('constructionRequest');
});

// --- Réponse du CLIENT à un devis de chantier (F7.3.e2) ----------------------
// Hors garde `gerer:chantiers` : accepter un devis est l'engagement du client,
// l'autorisation passe par la policy `respond` (client propriétaire seul).
Route::middleware('auth:sanctum')->prefix('construction-quotes')->group(function () {
    Route::patch('/{quote}/accept', [ConstructionQuoteController::class, 'accept'])->whereNumber('quote');
    Route::patch('/{quote}/refuse', [ConstructionQuoteController::class, 'refuse'])->whereNumber('quote');
});

// --- Suivi de chantier par les agents (permission gerer:chantiers) ------------
Route::middleware(['auth:sanctum', 'can:gerer:chantiers'])->prefix('construction-requests')->group(function () {
    Route::post('/{constructionRequest}/reports', [ConstructionReportController::class, 'store'])->whereNumber('constructionRequest');

    // Jalons (F7.3.e1) : le planning se pilote depuis le chantier. La route de
    // réordonnancement porte la liste ordonnée des jalons (et non une position par
    // jalon, cf. ConstructionMilestoneController::reorder).
    Route::post('/{constructionRequest}/milestones', [ConstructionMilestoneController::class, 'store'])->whereNumber('constructionRequest');
    Route::put('/{constructionRequest}/milestones/reorder', [ConstructionMilestoneController::class, 'reorder'])->whereNumber('constructionRequest');

    // Chiffrage d'un devis (F7.3.e2).
    Route::post('/{constructionRequest}/quotes', [ConstructionQuoteController::class, 'compose'])->whereNumber('constructionRequest');

    // Affectation d'un prestataire BTP à un lot (F7.3.e3).
    Route::post('/{constructionRequest}/assignments', [ConstructionAssignmentController::class, 'store'])->whereNumber('constructionRequest');
});

// --- Envoi d'un devis au client (F7.3.e2, permission gerer:chantiers) ---------
Route::middleware(['auth:sanctum', 'can:gerer:chantiers'])->prefix('construction-quotes')->group(function () {
    Route::patch('/{quote}/send', [ConstructionQuoteController::class, 'send'])->whereNumber('quote');
});

// --- Pilotage d'un jalon précis (F7.3.e1) ------------------------------------
// Hors du préfixe `construction-requests` : l'identifiant du jalon suffit à le
// retrouver, et l'agent agit dessus sans avoir à répéter celui du chantier.
Route::middleware(['auth:sanctum', 'can:gerer:chantiers'])->prefix('construction-milestones')->group(function () {
    Route::patch('/{milestone}', [ConstructionMilestoneController::class, 'update'])->whereNumber('milestone');
    Route::delete('/{milestone}', [ConstructionMilestoneController::class, 'destroy'])->whereNumber('milestone');
});
