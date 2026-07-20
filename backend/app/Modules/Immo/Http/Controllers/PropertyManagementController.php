<?php

namespace App\Modules\Immo\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Immo\Enums\PropertyStatus;
use App\Modules\Immo\Events\PropertyCreated;
use App\Modules\Immo\Http\Requests\StorePropertyDocumentRequest;
use App\Modules\Immo\Http\Requests\StorePropertyRequest;
use App\Modules\Immo\Http\Requests\UpdatePropertyRequest;
use App\Modules\Immo\Http\Resources\PropertyDocumentResource;
use App\Modules\Immo\Http\Resources\PropertyResource;
use App\Modules\Immo\Models\Property;
use App\Modules\Immo\Models\PropertyDocument;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gestion privée des biens par leur propriétaire (phase B2.3).
 *
 * Contrairement au catalogue public, ces endpoints montrent les biens du
 * propriétaire quel que soit leur statut (y compris en attente de validation).
 * L'isolation est garantie par la PropertyPolicy.
 */
class PropertyManagementController extends Controller
{
    /**
     * Mes biens (tous statuts confondus). GET /api/v1/properties/mine
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        $properties = Property::query()
            ->where('owner_id', $request->user()->id)
            ->with(['region', 'department', 'commune', 'owner', 'stay', 'media'])
            ->latest()
            ->paginate(15);

        return PropertyResource::collection($properties);
    }

    /**
     * Détail d'UN de mes biens (tous statuts). GET /api/v1/properties/mine/{property}
     *
     * Contrairement au détail public (`PropertyCatalogController@show`, restreint
     * aux biens publiés), cet endpoint expose le bien au propriétaire quel que
     * soit son statut — y compris en attente de validation ou rejeté. L'isolation
     * est garantie en scoppant la requête sur `owner_id` : un bien qui n'appartient
     * pas à l'utilisateur renvoie 404 (aucune fuite d'existence).
     */
    public function show(Request $request, Property $property): PropertyResource
    {
        abort_unless($property->owner_id === $request->user()->id, 404);

        return PropertyResource::make(
            $property->load(['region', 'department', 'commune', 'owner', 'stay', 'media']),
        );
    }

    /**
     * Dépôt d'un nouveau bien. POST /api/v1/properties
     *
     * Le bien est rattaché au propriétaire connecté et démarre en
     * `en_attente_validation` (jamais publié directement).
     */
    public function store(StorePropertyRequest $request): JsonResponse
    {
        $property = Property::create([
            ...$request->validated(),
            'owner_id' => $request->user()->id,
            'status' => PropertyStatus::EN_ATTENTE_VALIDATION->value,
        ]);

        activity()->causedBy($request->user())->performedOn($property)->log('Dépôt de bien');

        // Met le bien en file de validation (notifie les agents habilités).
        PropertyCreated::dispatch($property);

        return ApiResponse::created([
            'property' => PropertyResource::make($property->load(['region', 'department', 'commune', 'owner', 'stay', 'media'])),
        ]);
    }

    /**
     * Mise à jour d'un bien. PATCH /api/v1/properties/{property}
     * (autorisation gérée dans UpdatePropertyRequest via la policy).
     */
    public function update(UpdatePropertyRequest $request, Property $property): JsonResponse
    {
        $previousPrice = $property->price_xof;
        $property->update($request->validated());

        // Audit renforcé (B15.3) : toute modification de PRIX est tracée à part,
        // avec l'ancienne et la nouvelle valeur.
        if ($property->wasChanged('price_xof')) {
            activity()->causedBy($request->user())->performedOn($property)
                ->withProperties(['from_xof' => $previousPrice, 'to_xof' => $property->price_xof])
                ->log('Modification de prix');
        } else {
            activity()->causedBy($request->user())->performedOn($property)->log('Modification de bien');
        }

        return ApiResponse::success([
            'property' => PropertyResource::make($property->fresh()->load(['region', 'department', 'commune', 'owner', 'stay', 'media'])),
        ]);
    }

    /**
     * Ajout d'un document au bien. POST /api/v1/properties/{property}/documents
     */
    public function storeDocument(StorePropertyDocumentRequest $request, Property $property): JsonResponse
    {
        $file = $request->file('file');

        // Stockage privé, rangé par bien, nom de fichier aléatoire.
        $path = $file->store("properties/{$property->id}/documents", 'local');

        $document = $property->documents()->create([
            'type' => $request->validated()['type'],
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        activity()->causedBy($request->user())->performedOn($document)->log('Dépôt de document de bien');

        return ApiResponse::created([
            'document' => PropertyDocumentResource::make($document),
        ]);
    }

    /**
     * Téléchargement d'un document via URL signée.
     * GET /api/v1/properties/{property}/documents/{document}/download
     */
    public function downloadDocument(Property $property, PropertyDocument $document): StreamedResponse
    {
        // Le document doit bien appartenir au bien indiqué dans l'URL.
        abort_unless($document->property_id === $property->id, 404);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }
}
