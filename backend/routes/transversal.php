<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\ReviewController;
use App\Modules\Admin\Http\Controllers\FaqController;
use App\Modules\Admin\Http\Controllers\PageController;
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

// --- Avis publiés : consultation publique (B12.2) ----------------------------
Route::get('reviews', [ReviewController::class, 'index']);

// --- Webhook PayTech (B14.3) : public mais signé (HMAC vérifié en interne) ----
Route::post('payments/webhook', [PaymentWebhookController::class, 'handle']);

// --- Contenu éditorial public (B13.4) ----------------------------------------
// FAQ publiée et pages de contenu (adressées par slug). Édition = back-office.
Route::get('faqs', [FaqController::class, 'published']);
Route::get('pages/{page}', [PageController::class, 'show']);

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

    // --- Paiement : initiation (B14.2) ---------------------------------------
    // Crée l'intention côté PSP et renvoie l'URL de redirection. La confirmation
    // n'arrive que par webhook vérifié (B14.3). `throttle:payment` (B15.1).
    Route::post('payments/initiate', [PaymentController::class, 'initiate'])
        ->middleware(['throttle:payment', 'verified.account']);

    // --- Médias (B12.1) ------------------------------------------------------
    // Dépôt (image compressée ou vidéo par URL) et suppression. L'autorisation
    // « propriétaire de la ressource » est déléguée à la policy `update` du
    // module concerné, côté contrôleur.
    Route::post('media/upload', [MediaController::class, 'store']);
    Route::delete('media/{media}', [MediaController::class, 'destroy'])->whereNumber('media');

    // --- Avis (B12.2) --------------------------------------------------------
    // Dépôt réservé à qui a consommé la ressource (policy create côté contrôleur).
    Route::post('reviews', [ReviewController::class, 'store']);

    // --- Modération des avis (B12.3) -----------------------------------------
    // Publier/rejeter (agents/admin via policy moderate) ; la publication
    // répercute la note sur le prestataire concerné.
    Route::patch('reviews/{review}/moderate', [ReviewController::class, 'moderate'])
        ->whereNumber('review');
});
