<?php

use App\Modules\Manage\Http\Controllers\ManageController;
use App\Modules\Manage\Http\Controllers\MandateManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes du module Manage (gestion locative)
|--------------------------------------------------------------------------
|
| Deux espaces :
|  - Lecture (espace propriétaire, phase B4.5) : auth seule, scoping par owner
|    côté contrôleur/policy. Le rapport mensuel (B4.6) est ici car en lecture.
|  - Gestion (agents, phase B4.6) : permission `gerer:gestion-locative` requise.
|
*/

// --- Espace propriétaire & lecture (auth) ------------------------------------
Route::middleware('auth:sanctum')->prefix('manage')->group(function () {
    Route::get('/dashboard', [ManageController::class, 'dashboard']);
    Route::get('/mandates/mine', [ManageController::class, 'mine']);
    Route::get('/mandates/{mandate}', [ManageController::class, 'show'])->whereNumber('mandate');
    Route::get('/mandates/{mandate}/report', [ManageController::class, 'report'])->whereNumber('mandate');
});

// --- Gestion par les agents (permission gerer:gestion-locative) ---------------
Route::middleware(['auth:sanctum', 'can:gerer:gestion-locative'])->prefix('manage')->group(function () {
    Route::post('/mandates', [MandateManagementController::class, 'storeMandate']);

    Route::post('/mandates/{mandate}/rents', [MandateManagementController::class, 'storeRent'])->whereNumber('mandate');
    Route::patch('/rents/{rent}/pay', [MandateManagementController::class, 'markRentPaid'])->whereNumber('rent');

    Route::post('/mandates/{mandate}/incidents', [MandateManagementController::class, 'storeIncident'])->whereNumber('mandate');
    Route::patch('/incidents/{incident}/resolve', [MandateManagementController::class, 'resolveIncident'])->whereNumber('incident');

    Route::post('/mandates/{mandate}/expenses', [MandateManagementController::class, 'storeExpense'])->whereNumber('mandate');

    Route::post('/mandates/{mandate}/payouts', [MandateManagementController::class, 'storePayout'])->whereNumber('mandate');
    // ⚠️ POST et non PATCH (2026-08-06) : le constat porte désormais un
    // JUSTIFICATIF obligatoire, donc un `multipart/form-data` — que PHP ne
    // décode que sur un POST (`$_FILES` reste vide sur un PATCH).
    Route::post('/payouts/{payout}/pay', [MandateManagementController::class, 'markPayoutPaid'])->whereNumber('payout');
});

// Téléchargement du justificatif d'un reversement propriétaire. HORS du groupe
// authentifié : l'accès est prouvé par la SIGNATURE de l'URL (10 min, produite
// par OwnerPayoutResource), comme le KYC, les certifications et les versements
// partenaires. ⚠️ Le nom de route doit rester celui employé par la ressource.
Route::get('manage/payouts/{payout}/proof', [MandateManagementController::class, 'downloadPayoutProof'])
    ->name('manage.payouts.proof')
    ->whereNumber('payout')
    ->middleware('signed');
