<?php

use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // Toute l'API est versionnée sous le préfixe "api/v1".
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Applique la limitation de débit à TOUTES les routes API.
        // Le limiteur nommé "api" est défini dans app/Providers/AppServiceProvider.php.
        $middleware->api(append: [
            'throttle:api',
        ]);

        // Alias du garde « compte vérifié » (B15.2), appliqué aux actions
        // sensibles (réservation, paiement, publication).
        $middleware->alias([
            'verified.account' => \App\Http\Middleware\EnsureAccountVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Toute erreur survenant sur l'API (/api/*) est renvoyée en JSON.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // --- Format d'erreur standard de l'API ---
        // On mappe les exceptions les plus courantes vers un message clair et un
        // code HTTP cohérent, pour offrir au frontend un contrat d'erreur stable
        // (même en développement). Chaque handler ne s'applique qu'aux routes API ;
        // sinon il retourne null et Laravel reprend son comportement par défaut.
        //
        // Cas particulier : la validation (422) est laissée à Laravel, qui produit
        // déjà le format attendu { "message": "...", "errors": { champ: [...] } }.

        // 401 — requête non authentifiée (token absent ou invalide).
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Non authentifié.', 401);
            }
        });

        // 403 — action interdite par les policies / autorisations.
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Action non autorisée.', 403);
            }
        });
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Action non autorisée.', 403);
            }
        });

        // 404 — ressource (modèle Eloquent) ou route introuvable.
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Ressource introuvable.', 404);
            }
        });
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Ressource introuvable.', 404);
            }
        });
    })->create();
