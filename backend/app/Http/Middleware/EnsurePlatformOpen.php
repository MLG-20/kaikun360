<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fermeture d'accès avant ouverture officielle (2026-08-14).
 *
 * Tant que `platform.gate_enabled` est actif, TOUTE l'API `/api/v1` est
 * fermée par défaut — seuls un petit nombre de chemins publics (liste
 * d'attente, contact, FAQ, pages légales, connexion…) et le back-office
 * restent joignables ; le reste exige la permission directe
 * `acces:plateforme` (le super_admin passe toujours, via `Gate::before`).
 *
 * ⚠️ **Liste blanche volontairement COURTE et explicite** : à l'inverse d'un
 * garde qui protégerait quelques routes sensibles, celui-ci ferme tout par
 * défaut — le risque n'est donc pas d'oublier de protéger une route neuve,
 * mais d'oublier d'AUTORISER un chemin public légitime. Si une future page
 * publique doit rester ouverte pendant une fermeture, l'ajouter ici.
 *
 * ⚠️ Appliqué globalement via `$middleware->api(append: [...])`, donc AVANT
 * tout `auth:sanctum` de route — `$request->user('sanctum')` résout quand
 * même le jeton Bearer s'il est présent (le guard Sanctum ne dépend pas de
 * l'ordre des middlewares pour répondre à `user()`).
 *
 * Le personnel du back-office n'est jamais concerné : `admin/*` est exempté
 * dans son ensemble, quel que soit son rôle exact (agent/admin/super_admin).
 */
class EnsurePlatformOpen
{
    /**
     * @var list<string>
     */
    private const ALLOWLIST = [
        'admin/*',
        'auth/*',
        'waitlist',
        'contact',
        'contact-info',
        'faqs',
        'pages/*',
        'heroes',
        'platform-status',
        'version',
        'whatsapp/link',
        'payments/webhook',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! Settings::get('platform.gate_enabled', false)) {
            return $next($request);
        }

        if ($request->is($this->prefixed())) {
            return $next($request);
        }

        $user = $request->user('sanctum');

        if ($user !== null && $user->can('acces:plateforme')) {
            return $next($request);
        }

        return ApiResponse::error("L'accès à la plateforme n'est pas encore ouvert.", 423);
    }

    /**
     * `$request->is()` compare au chemin COMPLET (avec le préfixe `api/v1/`) :
     * la liste blanche, elle, est écrite sans préfixe pour rester lisible.
     *
     * @return list<string>
     */
    private function prefixed(): array
    {
        return array_map(fn (string $path) => "api/v1/{$path}", self::ALLOWLIST);
    }
}
