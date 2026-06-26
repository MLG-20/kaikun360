<?php

namespace App\Modules\Immo\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Immo\Enums\PropertyStatus;
use App\Modules\Immo\Http\Resources\PropertyResource;
use App\Modules\Immo\Models\Property;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Favoris de l'utilisateur connecté (phase B2.5).
 *
 * Un utilisateur peut sauvegarder des biens pour les retrouver plus tard.
 */
class FavoriteController extends Controller
{
    /**
     * Liste de mes favoris. GET /api/v1/favorites
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $favorites = $request->user()
            ->favoriteProperties()
            ->with(['region', 'department', 'commune', 'owner'])
            ->latest('favorites.created_at')
            ->paginate(15);

        return PropertyResource::collection($favorites);
    }

    /**
     * Ajoute un bien aux favoris. POST /api/v1/properties/{property}/favorite
     *
     * On ne peut mettre en favori qu'un bien publié. L'opération est idempotente
     * (syncWithoutDetaching) : favoriser deux fois ne crée pas de doublon.
     */
    public function store(Request $request, Property $property): JsonResponse
    {
        abort_unless($property->status === PropertyStatus::PUBLIE, 404);

        $request->user()->favoriteProperties()->syncWithoutDetaching([$property->id]);

        return ApiResponse::success(['message' => 'Bien ajouté aux favoris.']);
    }

    /**
     * Retire un bien des favoris. DELETE /api/v1/properties/{property}/favorite
     */
    public function destroy(Request $request, Property $property): JsonResponse
    {
        $request->user()->favoriteProperties()->detach($property->id);

        return ApiResponse::success(['message' => 'Bien retiré des favoris.']);
    }
}
