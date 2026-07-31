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
     * Dossier d'un avis. GET /api/v1/admin/reviews/{review}
     *
     * **F8.2.d — modérer, c'est arbitrer, pas trier.** La file affiche un
     * commentaire tronqué dans une cellule ; publier ou rejeter sur cette base
     * revient à jouer à pile ou face. Deux éléments manquent, et ce sont eux qui
     * tranchent :
     *
     *   - **le contexte de la ressource** : les autres avis déjà publiés sur
     *     elle. Une plainte isolée au milieu de quinze avis à cinq étoiles n'est
     *     pas un signal ; la troisième plainte identique en un mois en est un.
     *     C'est la différence entre modérer un texte et repérer un problème réel.
     *   - **l'auteur et sa réservation** : le commentaire complet, non tronqué,
     *     et de quoi vérifier que l'avis émane bien d'un client servi.
     *
     * La décision elle-même reste servie par
     * `PATCH /reviews/{review}/moderate`, qui la trace.
     */
    public function show(Review $review): JsonResponse
    {
        $review->load(['author', 'reviewable']);

        // Les autres avis PUBLIÉS de la même ressource : le contexte qui dit si
        // ce commentaire est un cas isolé ou le énième du même genre.
        $siblings = Review::query()
            ->where('reviewable_type', $review->reviewable_type)
            ->where('reviewable_id', $review->reviewable_id)
            ->where('id', '!=', $review->id)
            ->where('status', ReviewStatus::PUBLIE->value)
            ->with('author')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Review $other) => [
                'id' => $other->id,
                'rating' => $other->rating,
                'comment' => $other->comment,
                'author_name' => $other->author?->name,
                'created_at' => $other->created_at?->toIso8601String(),
            ]);

        $published = $siblings->count();
        $ratings = $siblings->pluck('rating');

        return ApiResponse::success([
            'review' => [
                'id' => $review->id,
                'reference' => $review->reference,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'status' => $review->status?->value,
                'status_label' => $review->status?->label(),
                'created_at' => $review->created_at?->toIso8601String(),
                'author' => $review->author
                    ? [
                        'id' => $review->author->id,
                        'name' => $review->author->name,
                        'email' => $review->author->email,
                        'phone' => $review->author->phone,
                    ]
                    : null,
            ],
            'resource' => [
                'type' => array_search($review->reviewable_type, Review::TYPES, true) ?: 'ressource',
                'label' => $this->resourceLabel($review),
                'id' => $review->reviewable_id,
                // Un avis sur un PRESTATAIRE ouvre sa fiche : c'est là que la
                // sanction se décide. Les autres types n'ont pas cette bascule.
                'is_provider' => $review->reviewable_type === Review::TYPES['provider'],
            ],
            'context' => [
                'published_count' => $published,
                'average' => $published > 0 ? round($ratings->avg(), 1) : null,
                // Avis publiés à 1 ou 2 étoiles : le signal que l'on cherche.
                'negative_count' => $ratings->filter(fn (int $r) => $r <= 2)->count(),
                'reviews' => $siblings,
            ],
        ]);
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
