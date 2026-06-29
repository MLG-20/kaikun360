<?php

namespace App\Modules\Mobility\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Mobility\Enums\VehicleType;
use App\Modules\Mobility\Http\Resources\VehicleResource;
use App\Modules\Mobility\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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
     */
    public function index(Request $request): AnonymousResourceCollection
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

        $query = Vehicle::query()->published();

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
        );
    }

    /**
     * Détail d'un véhicule publié. GET /api/v1/vehicles/{id}
     */
    public function show(string $id): VehicleResource
    {
        $vehicle = Vehicle::query()->published()->findOrFail($id);

        return VehicleResource::make($vehicle);
    }
}
