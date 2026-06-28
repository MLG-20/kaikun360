<?php

use App\Modules\Explore\Http\Controllers\ExperienceBookingController;
use App\Modules\Explore\Http\Controllers\ExperienceCatalogController;
use App\Modules\Explore\Http\Controllers\ExperienceManagementController;
use App\Modules\Explore\Http\Controllers\ExperienceValidationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes du module Explore (tourisme & expériences)
|--------------------------------------------------------------------------
|
| Catalogue PUBLIC (lecture) ; publication réservée aux prestataires vérifiés
| (policy) ; validation réservée aux agents (permission `valider:experience`).
| La réservation et l'annulation sont ajoutées en B6.3 / B6.4.
|
*/

// --- Catalogue public ---------------------------------------------------------
Route::prefix('experiences')->group(function () {
    Route::get('/', [ExperienceCatalogController::class, 'index']);
    Route::get('/{id}/availability', [ExperienceBookingController::class, 'availability'])->whereNumber('id');
});

// --- Espace prestataire & réservation (auth) ---------------------------------
Route::middleware('auth:sanctum')->prefix('experiences')->group(function () {
    Route::post('/', [ExperienceManagementController::class, 'store']);
    Route::get('/mine', [ExperienceManagementController::class, 'mine']);
    Route::post('/{id}/bookings', [ExperienceBookingController::class, 'store'])->whereNumber('id');
});

// --- Validation par les agents (permission valider:experience) ---------------
Route::middleware(['auth:sanctum', 'can:valider:experience'])->prefix('experiences')->group(function () {
    Route::patch('/{experience}/approve', [ExperienceValidationController::class, 'approve'])->whereNumber('experience');
    Route::patch('/{experience}/reject', [ExperienceValidationController::class, 'reject'])->whereNumber('experience');
});

// --- Détail public (après /mine pour éviter la capture de "mine") ------------
Route::get('experiences/{id}', [ExperienceCatalogController::class, 'show'])->whereNumber('id');
