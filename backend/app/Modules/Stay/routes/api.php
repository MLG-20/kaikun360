<?php

use App\Modules\Stay\Http\Controllers\StayBookingController;
use App\Modules\Stay\Http\Controllers\StayCatalogController;
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
    ->middleware('auth:sanctum');

Route::get('/stays/{id}', [StayCatalogController::class, 'show'])
    ->whereNumber('id');
