<?php

use App\Modules\Mobility\Http\Controllers\MobilityServiceBookingController;
use App\Modules\Mobility\Http\Controllers\MobilityServiceController;
use App\Modules\Mobility\Http\Controllers\VehicleBookingController;
use App\Modules\Mobility\Http\Controllers\VehicleCatalogController;
use App\Modules\Mobility\Http\Controllers\VehicleManagementController;
use App\Modules\Mobility\Http\Controllers\VehicleValidationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes du module Mobility (transport & mobilité)
|--------------------------------------------------------------------------
|
| Catalogue/recherche PUBLIC ; publication & gestion réservées aux prestataires
| (policy) ; validation réservée aux agents (permission `valider:vehicule`).
| La réservation (caution/commission) est ajoutée en B7.4.
|
*/

// --- Catalogue & recherche publics -------------------------------------------
Route::get('vehicles', [VehicleCatalogController::class, 'index']);
Route::get('mobility-services', [MobilityServiceController::class, 'index']);
// F8.10 — fiche d'un départ (avec son remplissage). Déclarée APRÈS la liste et
// contrainte au numérique : sans cela, `mobility-services/{id}` capterait tout
// segment littéral qu'on ajouterait plus tard à cette route.
Route::get('mobility-services/{id}', [MobilityServiceController::class, 'show'])
    ->whereNumber('id');

// --- Espace prestataire (auth + policy) --------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::post('vehicles', [VehicleManagementController::class, 'store'])->middleware('verified.account');
    Route::get('vehicles/mine', [VehicleManagementController::class, 'mine']);
    Route::patch('vehicles/{vehicle}', [VehicleManagementController::class, 'update'])->whereNumber('vehicle');

    // Réservations (caution & commission) — B7.4.
    Route::post('vehicles/{id}/bookings', [VehicleBookingController::class, 'store'])->whereNumber('id')->middleware('verified.account');
    Route::patch('vehicles/bookings/{booking}/cancel', [VehicleBookingController::class, 'cancel'])->whereNumber('booking');
    Route::post('mobility-services/{id}/bookings', [MobilityServiceBookingController::class, 'store'])->whereNumber('id')->middleware('verified.account');
});

// --- Validation par les agents (permission valider:vehicule) -----------------
Route::middleware(['auth:sanctum', 'can:valider:vehicule'])->group(function () {
    Route::patch('vehicles/{vehicle}/approve', [VehicleValidationController::class, 'approve'])->whereNumber('vehicle');
    Route::patch('vehicles/{vehicle}/reject', [VehicleValidationController::class, 'reject'])->whereNumber('vehicle');
});

// --- Détail public (après /mine pour éviter la capture) ----------------------
Route::get('vehicles/{id}', [VehicleCatalogController::class, 'show'])->whereNumber('id');
