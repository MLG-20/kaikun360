<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use App\Support\Heroes\HeroCatalog;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;

/**
 * Bande défilante des univers, juste sous le héros de l'accueil (F16.2) —
 * les NOMS seulement, pas une nouvelle grille de tuiles (déjà là, section 2).
 *
 * Route PUBLIQUE. Réutilise le catalogue d'univers de `HeroCatalog` (groupe
 * `univers`) comme unique source des libellés — pas une quatrième recopie de
 * cette liste. L'équipe ne fait que MASQUER ceux qu'elle ne veut pas voir
 * défiler (`home.universe_strip_hidden`) ; elle n'en invente pas de nouveaux.
 *
 * ⚠️ **F17 (2026-08-20)** : la plateforme appartenant au client, il doit
 * pouvoir y glisser un texte libre (annonce, actualité du moment) sans
 * attendre un déploiement. `home.universe_strip_custom_items` (ceux marqués
 * `active`) est ajouté À LA SUITE des noms d'univers, dans l'ordre où
 * l'équipe les a saisis. La réponse publique reste `{ names: string[] }` —
 * le frontend (`universe-strip.service.ts`) ne distingue pas un nom d'univers
 * d'une annonce, ce ne sont que des libellés qui défilent.
 */
class UniverseStripController extends Controller
{
    /**
     * GET /api/v1/universe-strip (public)
     */
    public function index(): JsonResponse
    {
        $masques = (array) Settings::get('home.universe_strip_hidden', []);

        $noms = collect(HeroCatalog::BANNERS)
            ->filter(fn (array $meta) => $meta['group'] === 'univers')
            ->reject(fn (array $meta, string $key) => in_array($key, $masques, true))
            ->map(fn (array $meta) => $meta['label'])
            ->values();

        $items = (array) Settings::get('home.universe_strip_custom_items', []);
        $custom = collect($items)
            ->filter(fn (array $item) => $item['active'] ?? false)
            ->map(fn (array $item) => $item['text']);

        return ApiResponse::success(['names' => $noms->concat($custom)->values()]);
    }
}
