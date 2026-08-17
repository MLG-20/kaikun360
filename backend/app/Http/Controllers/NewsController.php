<?php

namespace App\Http\Controllers;

use App\Http\Resources\NewsArticleResource;
use App\Models\NewsArticle;
use App\Support\ApiResponse;
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
        ]);
    }
}
