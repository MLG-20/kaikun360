<?php

namespace App\Http\Controllers;

use App\Models\HomeHeroSlide;
use App\Support\ApiResponse;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Héros de l'accueil (F15.1) — lecture publique.
 *
 * Distinct des bandeaux F12 : c'est le seul endroit du site à porter PLUSIEURS
 * photos (diaporama) ou une courte vidéo à la place. Règle appliquée côté
 * FRONTEND (pas ici) : une vidéo, quand elle existe, remplace entièrement le
 * diaporama — cet endpoint renvoie les deux, c'est à l'accueil de choisir.
 */
class HomeHeroController extends Controller
{
    /**
     * GET /api/v1/home-hero (public).
     */
    public function index(): JsonResponse
    {
        $videoPath = Settings::get('home.hero_video_path') ?: null;
        $videoUrl = Settings::get('home.hero_video_url') ?: null;

        return ApiResponse::success([
            'images' => HomeHeroSlide::ordered()->get()->map(fn (HomeHeroSlide $slide) => $slide->imageUrl())->values(),
            'video' => $videoPath || $videoUrl ? [
                'file' => $videoPath ? Storage::disk('public')->url($videoPath) : null,
                'url' => $videoPath ? null : $videoUrl,
            ] : null,
        ]);
    }
}
