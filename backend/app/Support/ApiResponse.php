<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Fabrique de réponses JSON standardisées pour toute l'API Kaikun 360.
 *
 * Objectif : garantir que CHAQUE endpoint renvoie exactement le même format,
 * afin que le frontend Angular puisse traiter toutes les réponses de manière
 * uniforme (succès comme erreurs).
 *
 * Formats produits :
 *   - Succès simple   : { "data": <contenu> }
 *   - Succès + méta    : { "data": <contenu>, "meta": {...} }
 *   - Liste paginée    : { "data": [...], "meta": {pagination}, "links": {...} }
 *   - Erreur           : { "message": "...", "errors": {...} }
 *
 * NB : la plupart des erreurs sont gérées automatiquement par le handler
 * global d'exceptions (bootstrap/app.php). La méthode error() ci-dessous
 * sert aux cas particuliers gérés manuellement dans un contrôleur.
 */
class ApiResponse
{
    /**
     * Réponse de succès générique.
     *
     * @param  mixed  $data    Le contenu utile renvoyé au client.
     * @param  array  $meta    Métadonnées éventuelles (contexte, totaux...).
     * @param  int    $status  Code HTTP de succès (200 par défaut).
     */
    public static function success(mixed $data = null, array $meta = [], int $status = 200): JsonResponse
    {
        $payload = ['data' => $data];

        // On n'ajoute la clé "meta" que si elle contient quelque chose,
        // pour ne pas alourdir inutilement les réponses simples.
        if (! empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * Réponse de ressource créée (HTTP 201), pour les POST de création.
     */
    public static function created(mixed $data = null, array $meta = []): JsonResponse
    {
        return self::success($data, $meta, 201);
    }

    /**
     * Réponse sans contenu (HTTP 204), typiquement après une suppression.
     */
    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Construit une réponse à partir d'un paginateur Laravel.
     * Produit automatiquement les clés data / meta / links attendues par le front.
     */
    public static function paginated(LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Réponse d'erreur générique au format standard de l'API.
     *
     * @param  string  $message  Message lisible (affichable à l'utilisateur).
     * @param  int     $status   Code HTTP (ex. 400, 403, 404, 409).
     * @param  array   $errors   Détail des erreurs, souvent indexé par champ.
     */
    public static function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $payload = ['message' => $message];

        if (! empty($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
