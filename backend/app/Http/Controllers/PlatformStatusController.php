<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * État de la fermeture d'accès avant ouverture officielle (2026-08-14).
 *
 * Route PUBLIQUE : un visiteur anonyme doit pouvoir savoir si la plateforme
 * est fermée avant même de se connecter — c'est ce que lit
 * `platformGateGuard` côté frontend à chaque navigation. Voir
 * App\Http\Middleware\EnsurePlatformOpen pour l'application réelle du
 * blocage (cette route ne fait qu'EN INFORMER, elle ne bloque rien elle-même).
 */
class PlatformStatusController extends Controller
{
    /**
     * GET /api/v1/platform-status (public)
     */
    public function show(Request $request): JsonResponse
    {
        $gateEnabled = (bool) Settings::get('platform.gate_enabled', false);

        // Pas de middleware `auth:sanctum` sur cette route (elle doit répondre
        // aux anonymes) : `user('sanctum')` résout quand même le jeton Bearer
        // s'il est présent, indépendamment de l'ordre des middlewares.
        $user = $request->user('sanctum');

        // `can()` déclenche Gate::before : le super_admin passe sans qu'on ait
        // à le nommer explicitement ici.
        $bypass = ! $gateEnabled || ($user !== null && $user->can('acces:plateforme'));

        return ApiResponse::success([
            'gate_enabled' => $gateEnabled,
            'bypass' => $bypass,
        ]);
    }
}
