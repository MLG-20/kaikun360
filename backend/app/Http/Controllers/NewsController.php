<?php

namespace App\Http\Controllers;

use App\Http\Resources\NewsArticleResource;
use App\Models\NewsArticle;
use App\Support\ApiResponse;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;

/**
 * Section « Actualités Kaikun » de la page d'accueil (F15) — lecture publique.
 *
 * Articles publiés uniquement, dans l'ordre choisi au back-office. Une liste
 * vide est un cas NORMAL (aucun article publié pour l'instant) : c'est ce
 * qui fait basculer l'accueil sur la grille des univers à la place (logique
 * côté frontend, `home-page.ts`).
 */
class NewsController extends Controller
{
    /**
     * GET /api/v1/news (public).
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success([
            'articles' => NewsArticleResource::collection(NewsArticle::published()->ordered()->get()),
            // Nombre de « petites cartes » à afficher dans la section « À
            // découvrir » de l'accueil — pilotable au back-office (F17,
            // demande client 2026-08-28), plus figé à 4 dans le frontend.
            'discoverCardsCount' => Settings::get('home.discover_cards_count', 4),
        ]);
    }

    /**
     * GET /api/v1/news/{newsArticle} (public) — détail d'un article publié.
     *
     * Le binding de route résout n'importe quel id existant, publié ou non :
     * on refuse ici explicitement un article non publié (404), comme
     * `PageController::show` le fait déjà pour les pages de contenu.
     */
    public function show(NewsArticle $newsArticle): JsonResponse
    {
        abort_unless($newsArticle->is_published, 404);

        return ApiResponse::success([
            'article' => new NewsArticleResource($newsArticle),
        ]);
    }
}
