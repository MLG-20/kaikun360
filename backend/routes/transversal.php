<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\WhatsAppLinkController;
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

// --- Lien WhatsApp click-to-chat contextuel (B16.3) : public --------------------
Route::get('whatsapp/link', [WhatsAppLinkController::class, 'generate']);

// --- Contenu éditorial public (B13.4) ----------------------------------------
// FAQ publiée et pages de contenu (adressées par slug). Édition = back-office.
Route::get('faqs', [FaqController::class, 'published']);
Route::get('pages/{page}', [PageController::class, 'show']);

// --- Contact public (F2.8.1) -------------------------------------------------
// Coordonnées du siège (lecture, pour affichage + carte) et dépôt de message
// ouvert à tous (prospect sans compte ; throttle anti-spam dédié). La
// consultation/traitement des messages sont réservés à l'équipe (routes admin).
Route::get('contact-info', [ContactController::class, 'info']);
Route::post('contact', [ContactController::class, 'store'])->middleware('throttle:10,1');

// --- Référentiel géographique public (F2.7.0) --------------------------------
// Régions / départements / communes du Sénégal, en LECTURE SEULE. Alimente les
// sélecteurs en cascade du dépôt de bien côté frontend. Départements et communes
// sont toujours filtrés par leur parent (region_id / department_id obligatoire).
Route::get('regions', [GeoController::class, 'regions']);
Route::get('departments', [GeoController::class, 'departments']);
Route::get('communes', [GeoController::class, 'communes']);

// --- Demandes génériques (B11.2) ---------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::post('requests', [RequestController::class, 'store']);
    Route::get('requests/my', [RequestController::class, 'my']);

    // Détail d'une de mes demandes (propriétaire uniquement — 403 sinon).
    Route::get('requests/{serviceRequest}', [RequestController::class, 'show'])
        ->whereNumber('serviceRequest');

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

    // Détail d'une de mes réservations (titulaire uniquement — 403 sinon).
    Route::get('bookings/{booking}', [BookingController::class, 'show'])
        ->whereNumber('booking');

    // --- Messagerie (F3.7) ---------------------------------------------------
    // Socle générique (conversations à participants + messages), scopé à
    // l'utilisateur courant côté contrôleur. La route fixe `unread-count` est
    // déclarée AVANT la paramétrée `{conversation}` (miroir de F3.6), et la
    // contrainte `whereNumber` lève toute ambiguïté de matching.
    Route::get('messages', [MessageController::class, 'index']);
    Route::get('messages/unread-count', [MessageController::class, 'unreadCount']);

    // Ouvrir un fil AVEC LE SUPPORT (F8.12) : le geste qui manquait depuis
    // F3.7. Ouvert à tous les profils connectés — c'est LE point d'entrée de la
    // messagerie côté client, prestataire, propriétaire ou entreprise.
    Route::post('messages/support', [MessageController::class, 'startWithSupport']);

    // Ouvrir un fil en DÉSIGNANT son destinataire : réservé à l'équipe
    // (`repondre:messages`). ⚠️ Cette route était ouverte à tous jusqu'en
    // F8.12 : n'importe quel compte pouvait écrire directement à n'importe quel
    // autre, ce qui contredit l'architecture retenue (« le fil naît toujours
    // chez Kaikun, c'est l'agent qui décide d'y faire entrer un tiers »).
    Route::post('messages', [MessageController::class, 'start'])
        ->middleware('can:repondre:messages');

    Route::get('messages/{conversation}', [MessageController::class, 'show'])
        ->whereNumber('conversation');
    Route::post('messages/{conversation}/messages', [MessageController::class, 'store'])
        ->whereNumber('conversation');

    // --- Favoris POLYMORPHES (tous univers) ----------------------------------
    // Généralisation des favoris (autrefois immobilier seul) : bien, nuitée,
    // véhicule, expérience, service de mobilité. `/ids` (fixe) est déclarée AVANT
    // toute route paramétrée pour lever l'ambiguïté de matching.
    Route::get('favorites', [FavoriteController::class, 'index']);
    Route::get('favorites/ids', [FavoriteController::class, 'ids']);
    Route::post('favorites', [FavoriteController::class, 'store']);
    Route::delete('favorites/{type}/{id}', [FavoriteController::class, 'destroy'])
        ->whereNumber('id');

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
    // Choix de l'image de couverture parmi les photos déjà déposées (F4.3).
    Route::patch('media/{media}/primary', [MediaController::class, 'setPrimary'])
        ->whereNumber('media');
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
