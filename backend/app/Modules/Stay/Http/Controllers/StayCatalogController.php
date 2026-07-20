<?php

namespace App\Modules\Stay\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Stay\Http\Resources\StayResource;
use App\Modules\Stay\Models\Stay;
use App\Support\Cache\CatalogCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catalogue PUBLIC des nuitées (phase B3.2).
 *
 * Ne montre que les nuitées réservables (scope bookable() : actives + bien
 * publié). Une nuitée inactive ou rattachée à un bien non publié reste invisible
 * et renvoie 404 au détail.
 */
class StayCatalogController extends Controller
{
    /**
     * Liste filtrable et paginée. GET /api/v1/stays
     *
     * Résultat mis en cache par jeu de filtres + page (B17.2). Invalidé dès
     * qu'une nuitée OU son bien change (voir Stay::booted() et Property::booted()).
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'region_id' => ['sometimes', 'integer', 'exists:regions,id'],
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'commune_id' => ['sometimes', 'integer', 'exists:communes,id'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'price_min' => ['sometimes', 'integer', 'min:0'],
            'price_max' => ['sometimes', 'integer', 'min:0'],
            'q' => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $cacheParams = $filters + ['page' => $request->integer('page', 1)];

        $payload = CatalogCache::remember('stays', $cacheParams, function () use ($filters) {
            $query = Stay::query()
                ->bookable()
                ->with(['property.region', 'property.department', 'property.commune', 'property.owner', 'property.media']);

            // Capacité minimale et fourchette de prix par nuit.
            $query->when($filters['capacity'] ?? null, fn ($q, $v) => $q->where('capacity', '>=', $v));
            $query->when($filters['price_min'] ?? null, fn ($q, $v) => $q->where('price_per_night_xof', '>=', $v));
            $query->when($filters['price_max'] ?? null, fn ($q, $v) => $q->where('price_per_night_xof', '<=', $v));

            // Filtres géographiques et recherche : portés par le bien associé.
            foreach (['region_id', 'department_id', 'commune_id'] as $geo) {
                $query->when($filters[$geo] ?? null, fn ($q, $v) => $q->whereHas('property', fn (Builder $p) => $p->where($geo, $v)));
            }
            $query->when($filters['q'] ?? null, fn ($q, $v) => $q->whereHas('property', fn (Builder $p) => $p->where('title', 'like', "%{$v}%")));

            $stays = $query->latest()->paginate($filters['per_page'] ?? 15)->withQueryString();

            return StayResource::collection($stays)->response()->getData(true);
        });

        return response()->json($payload);
    }

    /**
     * Détail d'une nuitée réservable. GET /api/v1/stays/{id}
     */
    public function show(string $id): StayResource
    {
        $stay = Stay::query()
            ->bookable()
            ->with(['property.region', 'property.department', 'property.commune', 'property.owner', 'property.media'])
            ->findOrFail($id);

        return StayResource::make($stay);
    }
}
