<?php

use App\Modules\Core\Http\Controllers\AuthController;
use App\Modules\Core\Http\Controllers\AvatarController;
use App\Modules\Core\Http\Controllers\DocumentController;
use App\Modules\Core\Http\Controllers\NotificationController;
use App\Modules\Core\Http\Controllers\PasswordResetController;
use App\Modules\Core\Http\Controllers\UserController;
use App\Modules\Core\Http\Controllers\VerificationController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes du module Core
|--------------------------------------------------------------------------
|
| Routes transverses de l'API (santé, version, etc.).
| Elles héritent automatiquement du préfixe "api/v1" et du middleware
| "throttle:api" définis globalement — inutile de les redéclarer ici.
|
*/

// GET /api/v1/version
// Endpoint public de "liveness" : permet de vérifier que l'API répond
// et de connaître sa version. Il sert aussi de test concret de l'enveloppe
// JSON standard (ApiResponse::success) mise en place en phase B0.4.
Route::get('/version', function () {
    return ApiResponse::success([
        'name' => config('app.name'),
        'api' => 'v1',
        'status' => 'ok',
    ]);
});

/*
| Authentification (phase B1.3).
| register/login sont publics ; logout exige un token Sanctum valide.
*/
// `throttle:auth` (10/min par IP, B15.1) durcit tout le groupe contre le
// bourrinage et l'énumération, en plus du throttle global.
Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    // F7.1.d — Second facteur du back-office (2FA e-mail admin/super_admin).
    Route::post('/two-factor', [AuthController::class, 'twoFactor']);
    Route::post('/google', [AuthController::class, 'google']); // connexion Google (B19)
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    // Vérification de compte (e-mail / téléphone) — utilisateur connecté (phase B1.4).
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/verify/send', [VerificationController::class, 'send']);
        Route::post('/verify', [VerificationController::class, 'verify']);
    });

    // Mot de passe oublié — public (phase B1.4).
    Route::post('/password/forgot', [PasswordResetController::class, 'forgot']);
    Route::post('/password/reset', [PasswordResetController::class, 'reset']);
});

/*
| Compte de l'utilisateur connecté (phase B1.5).
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users/me', [UserController::class, 'me']);
    Route::patch('/users/me', [UserController::class, 'update']);
    // Changement de mot de passe (F3.2b) : exige le mot de passe actuel.
    Route::patch('/users/me/password', [UserController::class, 'updatePassword']);
    // Suppression du compte sur demande (RGPD, B15.4) : anonymisation.
    Route::delete('/users/me', [UserController::class, 'destroy']);

    Route::get('/users/me/documents', [DocumentController::class, 'index']);
    Route::post('/users/me/documents', [DocumentController::class, 'store']);

    // Photo de profil / logo d'entreprise (F8.0). Multipart, champ `avatar`.
    // POST fait aussi office de remplacement (l'ancien fichier est supprimé) :
    // pas de PUT séparé pour une ressource qui n'existe qu'à zéro ou un exemplaire.
    Route::post('/users/me/avatar', [AvatarController::class, 'store']);
    Route::delete('/users/me/avatar', [AvatarController::class, 'destroy']);

    // Notifications "base de données" de l'espace client (F3.6).
    // Les routes fixes (unread-count, read-all) sont déclarées AVANT la route
    // paramétrée {notification}/read pour éviter toute ambiguïté de matching.
    Route::get('/users/me/notifications', [NotificationController::class, 'index']);
    Route::get('/users/me/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/users/me/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::patch('/users/me/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
});

// Téléchargement d'un document : accès via URL signée temporaire uniquement
// (pas de token requis, la signature fait foi). Le nom de route doit
// correspondre à celui utilisé dans UserDocumentResource.
Route::get('/users/me/documents/{document}/download', [DocumentController::class, 'download'])
    ->name('users.documents.download')
    ->middleware('signed');
