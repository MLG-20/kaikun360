<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Requests\StoreDocumentRequest;
use App\Modules\Core\Http\Resources\UserDocumentResource;
use App\Modules\Core\Models\UserDocument;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gestion des pièces justificatives de l'utilisateur connecté (phase B1.5).
 *
 * Les fichiers sont stockés sur un disque PRIVÉ. Le téléchargement n'est
 * possible que via une URL signée temporaire (route "download").
 */
class DocumentController extends Controller
{
    /**
     * Liste des documents de l'utilisateur. GET /api/v1/users/me/documents
     */
    public function index(Request $request): JsonResponse
    {
        $documents = $request->user()->documents()->latest()->get();

        return ApiResponse::success([
            'documents' => UserDocumentResource::collection($documents),
        ]);
    }

    /**
     * Dépôt d'une pièce. POST /api/v1/users/me/documents
     */
    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $user = $request->user();
        $file = $request->file('file');

        // Stockage sur le disque privé "local", rangé par utilisateur.
        // store() génère un nom de fichier aléatoire (pas de collision, pas d'injection).
        $path = $file->store("documents/{$user->id}", 'local');

        $document = $user->documents()->create([
            'type' => $request->validated()['type'],
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        // Journal d'audit : dépôt d'une pièce sensible.
        activity()->causedBy($user)->performedOn($document)->log('Dépôt de document');

        return ApiResponse::created([
            'document' => UserDocumentResource::make($document),
        ]);
    }

    /**
     * Téléchargement d'un fichier. GET /api/v1/users/me/documents/{document}/download
     *
     * Route protégée par le middleware "signed" : l'accès n'est possible
     * qu'avec une URL signée non expirée (générée par UserDocumentResource).
     */
    public function download(UserDocument $document): StreamedResponse
    {
        abort_unless(
            Storage::disk($document->disk)->exists($document->path),
            404
        );

        return Storage::disk($document->disk)
            ->download($document->path, $document->original_name);
    }
}
