<?php

namespace App\Modules\Immo\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Immo\Enums\PropertyType;
use App\Modules\Immo\Http\Resources\PropertyResource;
use App\Modules\Immo\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Catalogue PUBLIC des biens immobiliers (phase B2.2).
 *
 * Accessible sans authentification. Règle absolue : seuls les biens PUBLIÉS
 * sont visibles ici (via le scope Property::published()). Un bien en attente,
 * suspendu ou rejeté n'apparaît jamais et renvoie 404 au détail.
 */
class PropertyCatalogController extends Controller
{
    /**
     * Liste filtrable et paginée. GET /api/v1/properties
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'region_id' => ['sometimes', 'integer', 'exists:regions,id'],
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'commune_id' => ['sometimes', 'integer', 'exists:communes,id'],
            'type' => ['sometimes', Rule::in(PropertyType::values())],
            'tourist_zone' => ['sometimes', 'string', 'max:255'],
            'price_min' => ['sometimes', 'integer', 'min:0'],
            'price_max' => ['sometimes', 'integer', 'min:0'],
            'verification_level' => ['sometimes', 'string', 'max:50'],
            'q' => ['sometimes', 'string', 'max:255'],
            'sort' => ['sometimes', Rule::in(['recent', 'price_asc', 'price_desc'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Property::query()
            ->published() // <-- garantie : catalogue public = biens validés uniquement
            ->with(['region', 'department', 'commune', 'owner']);

        // Filtres géographiques.
        $query->when($filters['region_id'] ?? null, fn ($q, $v) => $q->where('region_id', $v));
        $query->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v));
        $query->when($filters['commune_id'] ?? null, fn ($q, $v) => $q->where('commune_id', $v));
        $query->when($filters['tourist_zone'] ?? null, fn ($q, $v) => $q->where('tourist_zone', $v));

        // Filtres type / prix / vérification.
        $query->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v));
        $query->when($filters['price_min'] ?? null, fn ($q, $v) => $q->where('price_xof', '>=', $v));
        $query->when($filters['price_max'] ?? null, fn ($q, $v) => $q->where('price_xof', '<=', $v));
        $query->when($filters['verification_level'] ?? null, fn ($q, $v) => $q->where('verification_level', $v));

        // Recherche plein-texte simple sur le titre.
        $query->when($filters['q'] ?? null, fn ($q, $v) => $q->where('title', 'like', "%{$v}%"));

        // Tri.
        match ($filters['sort'] ?? 'recent') {
            'price_asc' => $query->orderBy('price_xof'),
            'price_desc' => $query->orderByDesc('price_xof'),
            default => $query->latest(),
        };

        $properties = $query->paginate($filters['per_page'] ?? 15)->withQueryString();

        // Une collection de Resource paginée produit nativement l'enveloppe
        // standard { data: [...], links: {...}, meta: {...} }.
        return PropertyResource::collection($properties);
    }

    /**
     * Comparaison de plusieurs biens. GET /api/v1/properties/compare?ids=1,2,3
     *
     * Public. Ne renvoie que les biens publiés parmi les ids demandés
     * (4 maximum, pour garder une comparaison lisible).
     */
    public function compare(Request $request): AnonymousResourceCollection
    {
        // Parse "ids" (liste séparée par des virgules) → entiers uniques, max 4.
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($v) => (int) trim($v))
            ->filter()
            ->unique()
            ->take(4)
            ->values();

        $properties = Property::query()
            ->published()
            ->whereIn('id', $ids)
            ->with(['region', 'department', 'commune', 'owner'])
            ->get();

        return PropertyResource::collection($properties);
    }

    /**
     * Détail d'un bien publié. GET /api/v1/properties/{id}
     *
     * Un bien non publié n'est pas accessible publiquement → 404
     * (findOrFail sur le scope published()).
     */
    public function show(string $id): PropertyResource
    {
        $property = Property::query()
            ->published()
            ->with(['region', 'department', 'commune', 'owner'])
            ->findOrFail($id);

        return PropertyResource::make($property);
    }
}
