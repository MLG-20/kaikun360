<?php

namespace App\Modules\Explore\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Explore\Http\Resources\ExperienceResource;
use App\Modules\Explore\Models\TourismExperience;
use App\Support\Cache\CatalogCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Catalogue PUBLIC des expériences touristiques (phase B6.2).
 *
 * Accessible sans authentification. Seules les expériences PUBLIÉES sont
 * visibles (scope published()) ; une expérience non publiée renvoie 404.
 */
class ExperienceCatalogController extends Controller
{
    /**
     * Liste filtrable et paginée. GET /api/v1/experiences
     *
     * Résultat mis en cache par jeu de filtres + page (B17.2). Invalidé dès
     * qu'une expérience change (voir TourismExperience::booted()).
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'destination' => ['sometimes', 'string', 'max:255'],
            'price_min' => ['sometimes', 'integer', 'min:0'],
            'price_max' => ['sometimes', 'integer', 'min:0'],
            'duration_max' => ['sometimes', 'integer', 'min:1'],
            'q' => ['sometimes', 'string', 'max:255'],
            'sort' => ['sometimes', Rule::in(['recent', 'price_asc', 'price_desc'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $cacheParams = $filters + ['page' => $request->integer('page', 1)];

        $payload = CatalogCache::remember('experiences', $cacheParams, function () use ($filters) {
            $query = TourismExperience::query()->published();

            $query->when($filters['destination'] ?? null, fn ($q, $v) => $q->where('destination', $v));
            $query->when($filters['price_min'] ?? null, fn ($q, $v) => $q->where('price_xof', '>=', $v));
            $query->when($filters['price_max'] ?? null, fn ($q, $v) => $q->where('price_xof', '<=', $v));
            $query->when($filters['duration_max'] ?? null, fn ($q, $v) => $q->where('duration_days', '<=', $v));
            $query->when($filters['q'] ?? null, fn ($q, $v) => $q->where('title', 'like', "%{$v}%"));

            match ($filters['sort'] ?? 'recent') {
                'price_asc' => $query->orderBy('price_xof'),
                'price_desc' => $query->orderByDesc('price_xof'),
                default => $query->latest(),
            };

            return ExperienceResource::collection(
                $query->paginate($filters['per_page'] ?? 15)->withQueryString()
            )->response()->getData(true);
        });

        return response()->json($payload);
    }

    /**
     * Détail d'une expérience publiée. GET /api/v1/experiences/{id}
     */
    public function show(string $id): ExperienceResource
    {
        $experience = TourismExperience::query()->published()->findOrFail($id);

        return ExperienceResource::make($experience);
    }
}
