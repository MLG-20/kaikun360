<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * File de modération des avis pour le back-office (F7.2.g — CDC §6
 * « Avis et qualité »).
 *
 * Le `GET /reviews` public ne rend que les avis PUBLIÉS d'une ressource précise :
 * il ne permet pas à un modérateur de voir ce qui est en attente. Ce point
 * d'accès liste donc tous les avis, tous types confondus, filtrables par statut
 * (par défaut la file `en_attente`), pour alimenter l'écran de modération. La
 * décision publier/rejeter reste servie par `PATCH /reviews/{review}/moderate`.
 *
 * Réservé à la permission `moderer:avis`.
 */
class AdminReviewController extends Controller
{
    /**
     * Liste paginée et normalisée des avis à modérer. GET /api/v1/admin/reviews
     *
     * Filtres : `status` (en_attente / publie / rejete ; défaut = en_attente),
     * `q` (recherche dans le commentaire).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'string', Rule::in(ReviewStatus::values())],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $status = $request->string('status')->toString() ?: ReviewStatus::EN_ATTENTE->value;
        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));

        $reviews = Review::query()
            ->where('status', $status)
            ->when($request->filled('q'), function ($query) use ($request) {
                $query->where('comment', 'like', '%'.$request->string('q')->toString().'%');
            })
            // Anti-N+1 : l'auteur et la ressource notée servent au libellé de ligne.
            ->with(['author', 'reviewable'])
            ->latest()
            ->paginate($perPage)
            ->through(fn (Review $review) => [
                'id' => $review->id,
                'reference' => $review->reference,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'status' => $review->status?->value,
                'status_label' => $review->status?->label(),
                'author' => $review->author
                    ? ['id' => $review->author->id, 'name' => $review->author->name]
                    : null,
                'resource_type' => array_search($review->reviewable_type, Review::TYPES, true) ?: 'ressource',
                'resource_label' => $this->resourceLabel($review),
                'created_at' => $review->created_at?->toIso8601String(),
            ]);

        return ApiResponse::paginated($reviews);
    }

    /**
     * Libellé lisible de la ressource notée, robuste au type (bien / véhicule /
     * expérience / prestataire) : on prend le premier champ d'intitulé présent.
     */
    private function resourceLabel(Review $review): string
    {
        $resource = $review->reviewable;

        return $resource?->business_name
            ?? $resource?->title
            ?? $resource?->name
            ?? '#'.$review->reviewable_id;
    }
}
