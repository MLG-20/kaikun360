<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Modules\Pro\Enums\ProviderStatus;
use App\Modules\Pro\Models\ProviderCategory;
use App\Modules\Pro\Http\Resources\ProviderResource;
use App\Modules\Pro\Models\Provider;
use App\Modules\Pro\Models\ProviderMission;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

/**
 * Supervision des prestataires pour le back-office (F7.2.g — CDC §6
 * « Avis et qualité » : notation prestataire + sanctions).
 *
 * Fournit la liste filtrable des prestataires avec leur note agrégée
 * (`rating_avg`, `rating_count`), leur compteur d'avertissements et le motif de
 * sanction courant. Les actions de sanction elles-mêmes (avertir / suspendre)
 * restent servies par le module Pro (`PATCH /providers/{id}/warn|suspend`,
 * garde `valider:prestataire`) ; cet écran ne fait que les exposer.
 *
 * Réservé à la permission `valider:prestataire`.
 */
class AdminProviderController extends Controller
{
    /**
     * Liste paginée des prestataires. GET /api/v1/admin/providers
     *
     * Filtres : `status` (en_attente / valide / refuse / suspendu),
     * `q` (recherche sur le nom commercial), `category` (F7.2.k).
     *
     * `category` accepte **plusieurs valeurs séparées par une virgule**
     * (`?category=guide,restauration`). C'est ce qui permet à l'écran Tourisme
     * de restituer les **guides** et les **restaurants** du cahier des charges :
     * ils ne sont pas des entités du module Explore mais des **catégories de
     * prestataires** de la marketplace Pro (voir `ProviderCategory`).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', 'string', Rule::in(ProviderStatus::values())],
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        // Découpe et filtre la liste de catégories : une valeur inconnue est
        // ignorée plutôt que de faire échouer l'écran sur un libellé obsolète.
        $knownKeys = ProviderCategory::query()->pluck('key')->all();

        $categories = collect(explode(',', (string) $request->string('category')))
            ->map(fn (string $c) => trim($c))
            ->filter(fn (string $c) => in_array($c, $knownKeys, true))
            ->values()
            ->all();

        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));

        $providers = Provider::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            // Un filtre demandé dont AUCUNE valeur n'est valide doit renvoyer
            // une liste vide, jamais le catalogue entier : sans cela une faute
            // de frappe dans l'URL exposerait tous les prestataires.
            ->when($request->filled('category'), fn ($query) => $query->whereIn('category', $categories))
            ->when($request->filled('q'), function ($query) use ($request) {
                $query->where('business_name', 'like', '%'.$request->string('q')->toString().'%');
            })
            ->latest()
            ->paginate($perPage);

        return ProviderResource::collection($providers);
    }

    /**
     * Fiche d'un prestataire. GET /api/v1/admin/providers/{provider}
     *
     * **F8.2.c — pourquoi une fiche.** La liste (onglet « Guides & restaurants »
     * du Tourisme, écran Avis & qualité) affiche une note et un compteur
     * d'avertissements. Deux chiffres qui ne suffisent pas à décider : une
     * moyenne de 3,2 sur 40 avis ne dit pas la même chose que 3,2 sur deux, et
     * un avertissement sans son motif ne se défend pas devant l'intéressé.
     *
     * La fiche rassemble donc l'**identité et le contact** (le compte derrière
     * l'enseigne), les **certifications** déposées, les **avis reçus** en clair,
     * les **missions confiées** (F8.15.d) et le **journal** — où la sanction
     * figure avec sa raison.
     *
     * Lecture seule : avertir ou suspendre reste au module Pro
     * (`PATCH /providers/{id}/warn|suspend`), qui trace chaque décision.
     * ⚠️ **L'affectation de mission fait exception** (F8.15.d) : elle passe par
     * `POST /providers/{id}/missions`, appelé depuis cette même fiche. Ce n'est
     * pas une sanction mais un geste d'exploitation quotidien, et il se décide
     * précisément là où l'on juge le prestataire — sur sa note, ses avis et sa
     * charge en cours.
     */
    public function show(Provider $provider): JsonResponse
    {
        $provider->load(['user', 'certifications']);

        // Les avis portant sur le prestataire lui-même (F5.5) — distincts de
        // ceux qui notent une ressource réservée.
        $reviews = Review::query()
            ->where('reviewable_type', $provider->getMorphClass())
            ->where('reviewable_id', $provider->id)
            ->with('author:id,name')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (Review $review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'author_name' => $review->author?->name,
                'status' => $review->status?->value,
                'created_at' => $review->created_at?->toIso8601String(),
            ]);

        $activity = Activity::query()
            ->where('subject_type', $provider->getMorphClass())
            ->where('subject_id', $provider->id)
            ->with('causer')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (Activity $entry) => [
                'id' => $entry->id,
                'description' => $entry->description,
                'causer_name' => $entry->causer?->name,
                'properties' => $entry->properties,
                'created_at' => $entry->created_at,
            ]);

        return ApiResponse::success([
            'provider' => ProviderResource::make($provider),
            // Le compte derrière l'enseigne : c'est lui qu'on appelle.
            'account' => $provider->user === null ? null : [
                'id' => $provider->user->id,
                'name' => $provider->user->name,
                'email' => $provider->user->email,
                'phone' => $provider->user->phone,
            ],
            'reviews' => $reviews,
            'activity' => $activity,
            // F8.15.d — les missions confiées à ce prestataire. Elles manquaient
            // à la fiche : `POST /providers/{id}/missions` n'ayant jamais eu
            // d'appelant, aucune mission n'existait hors seeder et personne ne
            // les avait réclamées. Elles deviennent indispensables dès qu'on
            // peut en affecter une — sans elles on affecterait à l'aveugle,
            // sans voir ce que le prestataire a déjà sur les bras.
            'missions' => $provider->missions()
                ->with('client:id,name')
                ->latest()
                ->limit(30)
                ->get()
                ->map(fn (ProviderMission $mission) => [
                    'id' => $mission->id,
                    'reference' => $mission->reference,
                    'title' => $mission->title,
                    'amount_xof' => $mission->amount_xof,
                    'commission_xof' => $mission->commission_xof,
                    'status' => $mission->status?->value,
                    'status_label' => $mission->status?->label(),
                    'client_name' => $mission->client?->name,
                    'scheduled_at' => $mission->scheduled_at,
                    'created_at' => $mission->created_at,
                ]),
        ]);
    }
}
