<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\HomeHeroSlide;
use App\Modules\Admin\Http\Requests\StoreHomeHeroSlideRequest;
use App\Modules\Admin\Http\Requests\UpdateHomeHeroVideoRequest;
use App\Services\ImageProcessor;
use App\Support\ApiResponse;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Pilotage du héros de l'accueil (F15.1) — CDC §6 « Paramètres », même
 * permission que les bandeaux et les actualités : du contenu de vitrine.
 *
 * ⚠️ Distinct d'`AdminHeroController` (F12) : c'est le seul endroit du site où
 * l'équipe peut charger PLUSIEURS photos (diaporama) ou une courte vidéo à la
 * place. La vidéo est un SINGLETON (une seule à la fois) — stockée via
 * `Settings`, pas dans sa propre table, pour la même raison que
 * `platform.gate_enabled` : un réglage unique ne mérite pas une table.
 */
class AdminHomeHeroController extends Controller
{
    /**
     * Diaporama + vidéo actuels. GET /api/v1/admin/home-hero
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success($this->snapshot());
    }

    /**
     * Ajoute une photo au diaporama (à la fin). POST /api/v1/admin/home-hero/slides
     */
    public function storeSlide(StoreHomeHeroSlideRequest $request, ImageProcessor $images): JsonResponse
    {
        $stored = $images->storeCompressed(
            $request->file('image'),
            'home-hero',
            ImageProcessor::BACKGROUND_MAX_WIDTH,
            ImageProcessor::BACKGROUND_JPEG_QUALITY,
        );

        $position = (int) (HomeHeroSlide::max('position') ?? -1) + 1;

        HomeHeroSlide::create([
            'image_path' => $stored['path'],
            'position' => $position,
            'updated_by' => $request->user()->id,
        ]);

        activity()->causedBy($request->user())->log('Ajout d’une photo au héros de l’accueil');

        return ApiResponse::created($this->snapshot());
    }

    /**
     * Retire une photo du diaporama. DELETE /api/v1/admin/home-hero/slides/{slide}
     */
    public function destroySlide(HomeHeroSlide $slide): JsonResponse
    {
        Storage::disk('public')->delete($slide->image_path);

        activity()->causedBy(request()->user())->log('Retrait d’une photo du héros de l’accueil');

        $slide->delete();

        return ApiResponse::success($this->snapshot());
    }

    /**
     * Dépose ou retire la vidéo de fond. POST /api/v1/admin/home-hero/video
     *
     * ⚠️ POST et non PATCH — même piège multipart que les autres médias du
     * projet (`$_FILES` vide sur un PATCH).
     */
    public function updateVideo(UpdateHomeHeroVideoRequest $request): JsonResponse
    {
        $removing = (bool) $request->boolean('remove_video');
        $oldPath = Settings::get('home.hero_video_path') ?: null;

        if (($removing || $request->hasFile('video')) && $oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        if ($request->hasFile('video')) {
            $path = $request->file('video')->store('home-hero', 'public');
            Settings::set('home.hero_video_path', $path, $request->user());
            Settings::set('home.hero_video_url', null, $request->user());
        } elseif ($removing) {
            Settings::set('home.hero_video_path', null, $request->user());
            Settings::set('home.hero_video_url', null, $request->user());
        } elseif ($request->has('video_url')) {
            Settings::set('home.hero_video_path', null, $request->user());
            Settings::set('home.hero_video_url', $request->input('video_url') ?: null, $request->user());
        }

        activity()->causedBy($request->user())->log('Mise à jour de la vidéo du héros de l’accueil');

        return ApiResponse::success($this->snapshot());
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        $videoPath = Settings::get('home.hero_video_path') ?: null;
        $videoUrl = Settings::get('home.hero_video_url') ?: null;

        return [
            'slides' => HomeHeroSlide::ordered()->get()->map(fn (HomeHeroSlide $slide) => [
                'id' => $slide->id,
                'image' => $slide->imageUrl(),
            ])->values(),
            'video_file' => $videoPath ? Storage::disk('public')->url($videoPath) : null,
            'video_url' => $videoUrl,
        ];
    }
}
