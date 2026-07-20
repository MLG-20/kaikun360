<?php

use App\Modules\Stay\Http\Controllers\StayBookingController;
use App\Modules\Stay\Http\Controllers\StayCatalogController;
use App\Modules\Stay\Http\Controllers\StayManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes du module Stay (nuitées)
|--------------------------------------------------------------------------
|
| Héritent du préfixe "api/v1" et du middleware "throttle:api".
|
*/

// Catalogue PUBLIC des nuitées (phase B3.2). Seules les nuitées réservables
// (actives + bien publié) sont exposées.
Route::get('/stays', [StayCatalogController::class, 'index']);

// Disponibilité (calendrier) — public (phase B3.3).
Route::get('/stays/{id}/availability', [StayBookingController::class, 'availability'])
    ->whereNumber('id');

// Réservation d'une nuitée — utilisateur connecté (phase B3.3).
Route::post('/stays/{id}/bookings', [StayBookingController::class, 'store'])
    ->whereNumber('id')
    ->middleware(['auth:sanctum', 'verified.account']);

Route::get('/stays/{id}', [StayCatalogController::class, 'show'])
    ->whereNumber('id');

// Gestion de la config « nuitées » d'un bien par son propriétaire (F4.3).
// L'URI est nichée sous le bien (`/properties/{property}/stay`) : elle ne
// percute pas les routes du module Immo (segment `stay` non numérique). Le
// PUT exige un compte vérifié, comme le dépôt de bien.
Route::middleware('auth:sanctum')->group(function () {
    Route::put('/properties/{property}/stay', [StayManagementController::class, 'upsert'])
        ->whereNumber('property')
        ->middleware('verified.account');
    Route::delete('/properties/{property}/stay', [StayManagementController::class, 'destroy'])
        ->whereNumber('property');
});
