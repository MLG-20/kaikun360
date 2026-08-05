<?php

namespace App\Http\Controllers;

use App\Enums\ReviewStatus;
use App\Http\Requests\ModerateReviewRequest;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Services\RatingAggregator;
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

    /**
     * Mes avis, tous univers confondus. GET /api/v1/reviews/mine
     *
     * **F8.15.a — pourquoi cette route.** L'écran « Mes réservations » doit
     * savoir, pour chaque réservation terminée, si le client a **déjà** donné son
     * avis : sans quoi il proposerait indéfiniment « Donner mon avis » et le
     * client se heurterait au 422 « Vous avez déjà laissé un avis ».
     *
     * Elle ne peut pas se déduire de `GET /reviews` (qui ne renvoie que les avis
     * **publiés** : un avis encore en modération — l'état de tout avis frais —
     * n'y figure pas, et le client se croirait libre de recommencer). Elle évite
     * aussi de faire porter l'information par `BookingResource`, où « cet
     * utilisateur a-t-il noté cette cible ? » coûterait une requête par ligne de
     * liste. Ici, un seul appel couvre tout l'écran.
     *
     * Renvoie le couple (`reviewable_type`, `reviewable_id`) en clé courte, celle
     * que le front manipule déjà, jamais le nom de classe PHP.
     */
    public function mine(Request $request): JsonResponse
    {
        // Classe Eloquent → clé courte exposée à l'API (inverse de Review::TYPES).
        $slugs = array_flip(Review::TYPES);

        $reviews = Review::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (Review $review) => [
                'id' => $review->id,
                'reference' => $review->reference,
                'reviewable_type' => $slugs[$review->reviewable_type] ?? null,
                'reviewable_id' => $review->reviewable_id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'status' => $review->status?->value,
                'status_label' => $review->status?->label(),
                'created_at' => $review->created_at?->toIso8601String(),
            ]);

        return ApiResponse::success(['reviews' => $reviews]);
    }

    /**
     * Modère un avis en attente (agents/admin). Publie ou rejette, puis
     * répercute la note sur le prestataire concerné le cas échéant.
     * PATCH /api/v1/reviews/{review}/moderate
     */
    public function moderate(ModerateReviewRequest $request, Review $review, RatingAggregator $aggregator): JsonResponse
    {
        Gate::authorize('moderate', $review);

        // On ne modère qu'un avis encore en attente (pas de re-modération).
        if ($review->status !== ReviewStatus::EN_ATTENTE) {
            throw ValidationException::withMessages([
                'status' => ['Cet avis a déjà été modéré.'],
            ]);
        }

        $review->update([
            'status' => $request->validated()['status'],
            'moderated_by' => $request->user()->id,
            'moderated_at' => now(),
        ]);

        // Un avis nouvellement publié met à jour la note agrégée du prestataire.
        if ($review->status === ReviewStatus::PUBLIE) {
            $aggregator->syncFromReviewable($review->reviewable);
        }

        return ApiResponse::success(['review' => ReviewResource::make($review->fresh())]);
    }
}
