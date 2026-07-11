<?php

namespace App\Http\Controllers;

use App\Enums\ReviewStatus;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Avis polymorphes — couche transversale (phase B12.2).
 *
 * Dépôt d'un avis par un utilisateur ayant consommé la ressource (statut
 * `en_attente` → modération B12.3) et consultation publique des avis publiés.
 */
class ReviewController extends Controller
{
    /**
     * Dépose un avis. POST /api/v1/reviews
     */
    public function store(StoreReviewRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Résolution sûre de la ressource notée via l'allow-list.
        $reviewableClass = Review::TYPES[$data['reviewable_type']];
        $reviewable = $reviewableClass::findOrFail($data['reviewable_id']);

        // Règle du cahier : il faut avoir consommé le service (policy create).
        Gate::authorize('create', [Review::class, $reviewable]);

        // Un seul avis par utilisateur et par ressource.
        $exists = Review::where('user_id', $request->user()->id)
            ->where('reviewable_type', $reviewableClass)
            ->where('reviewable_id', $reviewable->getKey())
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'reviewable_id' => ['Vous avez déjà laissé un avis pour cette ressource.'],
            ]);
        }

        $review = Review::create([
            'reference' => 'REV-'.Str::upper(Str::random(8)),
            'user_id' => $request->user()->id,
            'reviewable_type' => $reviewableClass,
            'reviewable_id' => $reviewable->getKey(),
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'status' => ReviewStatus::EN_ATTENTE->value,
        ]);

        return ApiResponse::created(['review' => ReviewResource::make($review)]);
    }

    /**
     * Avis publiés d'une ressource + note agrégée. GET /api/v1/reviews
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'reviewable_type' => ['required', 'string', Rule::in(array_keys(Review::TYPES))],
            'reviewable_id' => ['required', 'integer', 'min:1'],
        ]);

        $query = Review::published()
            ->where('reviewable_type', Review::TYPES[$filters['reviewable_type']])
            ->where('reviewable_id', $filters['reviewable_id']);

        // Note moyenne / nombre calculés sur les avis publiés uniquement.
        $summary = [
            'average' => round((float) (clone $query)->avg('rating'), 2),
            'count' => (clone $query)->count(),
        ];

        return ApiResponse::success([
            'reviews' => ReviewResource::collection($query->latest()->get()),
            'summary' => $summary,
        ]);
    }
}
