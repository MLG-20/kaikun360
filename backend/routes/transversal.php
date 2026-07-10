<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes de la couche transversale (Requests, Quotes, Bookings) — B11
|--------------------------------------------------------------------------
|
| Ces routes ne relèvent d'aucun module métier : elles portent les notions
| partagées (demandes génériques, devis, réservations). Chargées par
| routes/api.php sous le préfixe /api/v1.
|
*/

// --- Demandes génériques (B11.2) ---------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::post('requests', [RequestController::class, 'store']);
    Route::get('requests/my', [RequestController::class, 'my']);

    // Changement de statut réservé aux agents/admin (machine à états).
    Route::patch('requests/{serviceRequest}/status', [RequestController::class, 'updateStatus'])
        ->whereNumber('serviceRequest')
        ->middleware('can:traiter:demandes');

    // --- Devis (B11.3) -------------------------------------------------------
    // Proposition d'un devis par les agents/admin.
    Route::post('requests/{serviceRequest}/quotes', [QuoteController::class, 'store'])
        ->whereNumber('serviceRequest')
        ->middleware('can:traiter:demandes');
    // Consultation & réponse (policy view/respond côté contrôleur).
    Route::get('quotes/{quote}', [QuoteController::class, 'show'])->whereNumber('quote');
    Route::patch('quotes/{quote}', [QuoteController::class, 'respond'])->whereNumber('quote');

    // --- Réservations (B11.3) ------------------------------------------------
    Route::get('bookings/my', [BookingController::class, 'my']);
});
