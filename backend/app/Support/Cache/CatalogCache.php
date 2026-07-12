<?php

namespace App\Support\Cache;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * B17.2 — Mise en cache Redis des catalogues et recherches publics.
 *
 * Les endpoints de catalogue (biens, nuitées, véhicules, expériences, mobilité)
 * sont les plus consultés et ne renvoient que du contenu PUBLIÉ, donc largement
 * partagé entre visiteurs anonymes. On met en cache le résultat rendu (tableau
 * JSON data/links/meta) par jeu de filtres.
 *
 * Invalidation par « versioning » : chaque catalogue possède un jeton de version
 * stocké en cache, injecté dans la clé de chaque entrée. Invalider un catalogue =
 * régénérer son jeton (O(1)) ; les anciennes entrées deviennent inatteignables et
 * expirent d'elles-mêmes par TTL. Ce procédé fonctionne sur n'importe quel store
 * (redis en prod, array en test) sans dépendre des tags Redis ni d'un balayage de
 * clés.
 *
 * Les modèles de catalogue régénèrent le jeton via {@see self::flush()} sur leurs
 * événements `saved`/`deleted` (voir les `booted()` respectifs), ce qui rend
 * l'invalidation automatique quel que soit le chemin d'écriture (création,
 * modification de prix, validation, suppression…).
 */
class CatalogCache
{
    /** Durée de vie d'une entrée de catalogue (secondes). Filet de sécurité : l'invalidation active reste la source de fraîcheur. */
    public const TTL = 300;

    /** Catalogues connus (sert de garde-fou et de documentation). */
    public const CATALOGS = ['properties', 'stays', 'vehicles', 'experiences', 'mobility'];

    /**
     * Retourne le résultat mémoïsé pour un catalogue + un jeu de paramètres.
     *
     * @param  array<string, mixed>  $params  Filtres validés + page courante.
     * @param  Closure():array<mixed>  $callback  Produit le tableau à mettre en cache (payload JSON).
     * @return array<mixed>
     */
    public static function remember(string $catalog, array $params, Closure $callback): array
    {
        return Cache::remember(self::key($catalog, $params), self::TTL, $callback);
    }

    /**
     * Invalide tout un catalogue en régénérant son jeton de version.
     */
    public static function flush(string $catalog): void
    {
        Cache::forever(self::versionKey($catalog), self::freshToken());
    }

    /**
     * Construit la clé d'une entrée : catalogue + version + empreinte des paramètres.
     *
     * @param  array<string, mixed>  $params
     */
    private static function key(string $catalog, array $params): string
    {
        ksort($params); // stabilise l'empreinte quel que soit l'ordre des filtres
        $fingerprint = md5(json_encode($params) ?: '');

        return "catalog:{$catalog}:{$fingerprint}:v".self::version($catalog);
    }

    /**
     * Jeton de version courant du catalogue (créé à la volée au premier accès).
     */
    private static function version(string $catalog): string
    {
        return Cache::rememberForever(self::versionKey($catalog), fn () => self::freshToken());
    }

    private static function versionKey(string $catalog): string
    {
        return "catalog:{$catalog}:version";
    }

    private static function freshToken(): string
    {
        return (string) Str::uuid();
    }
}
