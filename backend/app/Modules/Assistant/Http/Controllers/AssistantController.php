<?php

namespace App\Modules\Assistant\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assistant\Contracts\AssistantBrain;
use App\Modules\Assistant\Http\Requests\AssistantMessageRequest;
use App\Modules\Assistant\Support\AssistantContext;
use App\Modules\Assistant\Tools\ToolRegistry;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Point d'entrée unique de l'assistant (phase F10.0).
 *
 * Un seul endpoint pour toute la plateforme et tous les rôles. Ce n'est pas un
 * raccourci : c'est ce qui rend la surface d'attaque petite et vérifiable.
 * Un endpoint, un jeu de garde-fous, un endroit à auditer — plutôt qu'un
 * assistant par espace, chacun avec ses propres oublis.
 *
 * Ce qui varie d'un appelant à l'autre, ce n'est pas la route : c'est la
 * TROUSSE À OUTILS que le registre compose à partir du rôle (voir ToolRegistry).
 *
 * ── Ce que ce contrôleur ne fait PAS ────────────────────────────────────────
 * Il n'interroge aucune table métier et ne décide d'aucune autorisation. Il
 * valide, construit le contexte, délègue au cerveau, et met en forme. Toute la
 * sécurité des données reste dans les outils (scopes publics et policies) — là
 * où elle est déjà éprouvée.
 */
class AssistantController extends Controller
{
    /**
     * Envoie un message à l'assistant. POST /api/v1/assistant/messages
     *
     * L'endpoint est SANS ÉTAT : l'historique voyage avec la requête. Aucune
     * conversation d'assistant n'est stockée à ce stade — c'est un choix de
     * sobriété (rien à protéger, rien à purger) ; la journalisation des
     * échanges vers le back-office viendra avec les espaces connectés (F10.2),
     * là où elle a une valeur d'exploitation.
     */
    public function message(
        AssistantMessageRequest $request,
        AssistantBrain $brain,
        ToolRegistry $tools,
    ): JsonResponse {
        // L'interrupteur général (`ASSISTANT_ENABLED`) est vérifié en amont par
        // le middleware EnsureAssistantEnabled : il doit couper AVANT la
        // validation du Form Request, sinon un assistant désactivé répondrait
        // 422 sur une entrée malformée au lieu de 503.

        // Authentification FACULTATIVE : la route est publique, mais un porteur
        // de jeton valide doit être reconnu pour recevoir sa trousse d'outils.
        // D'où `user('sanctum')` explicite — le garde par défaut ne résoudrait
        // rien hors du middleware `auth:sanctum`.
        $context = AssistantContext::for($request->user('sanctum'));

        $reply = $brain->reply(
            $request->validated('message'),
            $request->cleanHistory(),
            $context,
            $tools,
        );

        return ApiResponse::success(['reply' => $reply->toArray()]);
    }
}
