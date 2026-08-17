<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\NewsArticle;
use App\Modules\Admin\Http\Requests\StoreNewsArticleRequest;
use App\Modules\Admin\Http\Requests\UpdateNewsArticleRequest;
use App\Modules\Admin\Http\Resources\NewsArticleResource;
use App\Services\ImageProcessor;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

/**
 * Pilotage de la section « Actualités Kaikun » de l'accueil (F15), CDC §6
 * « Paramètres » — même permission que les bandeaux et les pages de contenu :
 * c'est du contenu de vitrine, pas un dossier client.
 *
 * Sur l'accueil, cette section REMPLACE la grille des univers tant qu'au
 * moins un article est publié (voir HomeController) — l'équipe doit donc
 * pouvoir dépublier un article sans le supprimer, pour revenir sans perte à
 * la grille le temps de préparer le prochain contenu.
 */
class AdminNewsController extends Controller
{
    /**
     * Tous les articles, publiés ou non. GET /api/v1/admin/news
     */
    public function index(): AnonymousResourceCollection
    {
        return NewsArticleResource::collection(NewsArticle::ordered()->get());
    }

    /**
     * Crée un article. POST /api/v1/admin/news
     */
    public function store(StoreNewsArticleRequest $request, ImageProcessor $images): JsonResponse
    {
        $data = $request->safe()->only(['title', 'excerpt', 'body']);

        $stored = $images->storeCompressed(
            $request->file('image'),
            'news',
            ImageProcessor::MAX_WIDTH,
            ImageProcessor::JPEG_QUALITY,
        );
        $data['image_path'] = $stored['path'];

        // Le fichier déposé l'emporte sur l'URL d'embed — voir NewsArticle.
        if ($request->hasFile('video')) {
            $data['video_path'] = $request->file('video')->store('news', 'public');
        } else {
            $data['video_url'] = $request->input('video_url') ?: null;
        }

        $article = NewsArticle::create($data + [
            'is_published' => (bool) $request->boolean('is_published'),
            'position' => $request->input('position', 0),
            'updated_by' => $request->user()->id,
        ]);

        activity()->causedBy($request->user())->performedOn($article)
            ->log("Création de l'article « {$article->title} »");

        return ApiResponse::created(['article' => NewsArticleResource::make($article)]);
    }

    /**
     * Met à jour un article. POST /api/v1/admin/news/{news}
     *
     * En POST et non PATCH : multipart ne se décode que sur POST, exactement
     * le même piège déjà rencontré sur les justificatifs de reversement.
     */
    public function update(UpdateNewsArticleRequest $request, NewsArticle $news, ImageProcessor $images): JsonResponse
    {
        $data = $request->safe()->except(['image', 'video', 'video_url', 'remove_video']);

        foreach (['title', 'excerpt', 'body'] as $field) {
            if (array_key_exists($field, $data)) {
                $news->{$field} = $data[$field];
            }
        }

        if ($request->hasFile('image')) {
            if ($news->image_path) {
                Storage::disk('public')->delete($news->image_path);
            }
            $stored = $images->storeCompressed(
                $request->file('image'),
                'news',
                ImageProcessor::MAX_WIDTH,
                ImageProcessor::JPEG_QUALITY,
            );
            $news->image_path = $stored['path'];
        }

        $removingVideo = (bool) $request->boolean('remove_video');

        if (($removingVideo || $request->hasFile('video')) && $news->video_path) {
            Storage::disk('public')->delete($news->video_path);
            $news->video_path = null;
        }

        if ($request->hasFile('video')) {
            $news->video_path = $request->file('video')->store('news', 'public');
        }

        if ($request->has('video_url')) {
            $news->video_url = $request->input('video_url') ?: null;
        }

        if ($request->has('is_published')) {
            $news->is_published = $request->boolean('is_published');
        }

        if ($request->has('position')) {
            $news->position = (int) $request->input('position');
        }

        $news->updated_by = $request->user()->id;
        $news->save();

        activity()->causedBy($request->user())->performedOn($news)
            ->log("Mise à jour de l'article « {$news->title} »");

        return ApiResponse::success(['article' => NewsArticleResource::make($news->fresh())]);
    }

    /**
     * Supprime un article (et ses fichiers). DELETE /api/v1/admin/news/{news}
     */
    public function destroy(NewsArticle $news): JsonResponse
    {
        if ($news->image_path) {
            Storage::disk('public')->delete($news->image_path);
        }
        if ($news->video_path) {
            Storage::disk('public')->delete($news->video_path);
        }

        activity()->causedBy(request()->user())->performedOn($news)
            ->log("Suppression de l'article « {$news->title} »");

        $news->delete();

        return ApiResponse::noContent();
    }
}
