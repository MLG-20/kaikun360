<?php

namespace App\Modules\Mobility\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Mobility\Enums\VehicleType;
use App\Modules\Mobility\Http\Resources\VehicleResource;
use App\Modules\Mobility\Models\Vehicle;
use App\Support\Cache\CatalogCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Catalogue PUBLIC des véhicules (phase B7.3).
 *
 * Seuls les véhicules PUBLIÉS (validés) sont visibles ; un véhicule non publié
 * renvoie 404 au détail.
 */
class VehicleCatalogController extends Controller
{
    /**
     * Recherche filtrable et paginée. GET /api/v1/vehicles
     *
     * Résultat mis en cache par jeu de filtres + page (B17.2). Invalidé dès
     * qu'un véhicule change (voir Vehicle::booted()).
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'type' => ['sometimes', Rule::in(VehicleType::values())],
            'capacity_min' => ['sometimes', 'integer', 'min:1'],
            'price_max' => ['sometimes', 'integer', 'min:0'],
            'has_driver' => ['sometimes', 'boolean'],
            'q' => ['sometimes', 'string', 'max:255'],
            'sort' => ['sometimes', Rule::in(['recent', 'price_asc', 'price_desc'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $cacheParams = $filters + ['page' => $request->integer('page', 1)];

        $payload = CatalogCache::remember('vehicles', $cacheParams, function () use ($filters) {
            // F8.18 — `media` chargée en amont : la carte affiche la photo de
            // couverture, et sans ce `with` chaque ligne déclencherait sa propre
            // requête (N+1 sur la liste la plus consultée du site).
            $query = Vehicle::query()->published()->with('media');

            $query->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v));
            $query->when($filters['capacity_min'] ?? null, fn ($q, $v) => $q->where('capacity', '>=', $v));
            $query->when($filters['price_max'] ?? null, fn ($q, $v) => $q->where('price_per_day_xof', '<=', $v));
            $query->when(array_key_exists('has_driver', $filters), fn ($q) => $q->where('has_driver', $filters['has_driver']));
            $query->when($filters['q'] ?? null, fn ($q, $v) => $q->where('brand', 'like', "%{$v}%"));

            match ($filters['sort'] ?? 'recent') {
                'price_asc' => $query->orderBy('price_per_day_xof'),
                'price_desc' => $query->orderByDesc('price_per_day_xof'),
                default => $query->latest(),
            };

            return VehicleResource::collection(
                $query->paginate($filters['per_page'] ?? 15)->withQueryString()
            )->response()->getData(true);
        });

        return response()->json($payload);
    }

    /**
     * Détail d'un véhicule publié. GET /api/v1/vehicles/{id}
     */
    public function show(string $id): VehicleResource
    {
        // La fiche affiche la galerie ENTIÈRE (pas seulement la couverture).
        $vehicle = Vehicle::query()->published()->with('media')->findOrFail($id);

        return VehicleResource::make($vehicle);
    }
}
