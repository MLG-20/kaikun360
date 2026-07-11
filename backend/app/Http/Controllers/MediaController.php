<?php

namespace App\Http\Controllers;

use App\Enums\MediaType;
use App\Http\Requests\StoreMediaRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\ImageProcessor;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Médias polymorphes — couche transversale (phase B12.1).
 *
 * Un utilisateur dépose des images (compressées) ou des vidéos (URL) sur une
 * ressource dont il est propriétaire. L'autorisation réutilise la policy
 * `update` du module concerné (PropertyPolicy, VehiclePolicy, ExperiencePolicy…).
 */
class MediaController extends Controller
{
    /**
     * Dépose un média. POST /api/v1/media
     */
    public function store(StoreMediaRequest $request, ImageProcessor $images): JsonResponse
    {
        $data = $request->validated();

        // Résolution sûre de la cible via l'allow-list (jamais une classe libre).
        $mediableClass = Media::TYPES[$data['mediable_type']];
        $mediable = $mediableClass::findOrFail($data['mediable_id']);

        // Autorisation : il faut pouvoir « modifier » la ressource illustrée.
        Gate::authorize('update', $mediable);

        $isPrimary = (bool) ($data['is_primary'] ?? false);

        // Image téléversée → compression + stockage ; sinon vidéo par URL.
        $attributes = [
            'reference' => 'MED-'.Str::upper(Str::random(8)),
            'mediable_type' => $mediableClass,
            'mediable_id' => $mediable->getKey(),
            'uploaded_by' => $request->user()->id,
            'is_primary' => $isPrimary,
            'position' => $data['position'] ?? 0,
        ];

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $stored = $images->storeCompressed($file);

            $attributes += [
                'type' => MediaType::IMAGE->value,
                'disk' => 'public',
                'path' => $stored['path'],
                'url' => null,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => 'image/jpeg',       // normalisé par le compresseur
                'size_bytes' => $stored['size_bytes'],
            ];
        } else {
            $attributes += [
                'type' => MediaType::VIDEO->value,
                'disk' => 'public',
                'path' => null,
                'url' => $data['url'],
            ];
        }

        // Une seule image « de une » par ressource : on retire l'ancienne.
        if ($isPrimary) {
            Media::where('mediable_type', $mediableClass)
                ->where('mediable_id', $mediable->getKey())
                ->update(['is_primary' => false]);
        }

        $media = Media::create($attributes);

        return ApiResponse::created(['media' => MediaResource::make($media)]);
    }

    /**
     * Supprime un média (propriétaire de la ressource ou admin).
     * DELETE /api/v1/media/{media}
     */
    public function destroy(Media $media): JsonResponse
    {
        // La cible peut avoir disparu (ressource supprimée) ; dans ce cas seul un
        // admin peut faire le ménage. Sinon on réutilise la policy `update`.
        if ($media->mediable) {
            Gate::authorize('update', $media->mediable);
        } else {
            abort_unless(request()->user()?->hasRole('admin'), 403);
        }

        // On efface aussi le fichier physique s'il existe.
        if ($media->path) {
            Storage::disk($media->disk)->delete($media->path);
        }

        $media->delete();

        return ApiResponse::success(['deleted' => true]);
    }
}
