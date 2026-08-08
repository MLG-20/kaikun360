<?php

namespace App\Modules\Assistant\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interrupteur général de l'assistant (phase F10.0).
 *
 * Placé en MIDDLEWARE, et pas dans le contrôleur, pour une raison précise :
 * un Form Request est résolu avant l'action, donc une vérification faite dans
 * le contrôleur laisse la validation s'exécuter d'abord. Un assistant coupé
 * répondait alors 422 (« message trop long ») au lieu de 503 — message
 * trompeur, et surtout traitement exécuté alors qu'on voulait justement ne rien
 * exécuter.
 *
 * Ici, la coupure est franche : rien n'est validé, rien n'est instancié.
 * C'est ce qu'on attend d'un interrupteur d'incident.
 */
class EnsureAssistantEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('assistant.enabled', true)) {
            return ApiResponse::error("L'assistant est momentanément indisponible.", 503);
        }

        return $next($request);
    }
}
