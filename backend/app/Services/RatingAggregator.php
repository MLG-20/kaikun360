<?php

namespace App\Services;

use App\Models\Review;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Pro\Models\Provider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Agrégation de la notation prestataire à partir des avis (couche transversale, B12.3).
 *
 * Quand un avis est publié, on recalcule la note moyenne (`rating_avg`) et le
 * nombre d'avis (`rating_count`) du prestataire concerné, en n'agrégeant que les
 * avis **publiés** qui le concernent.
 *
 * Deux sources d'avis alimentent la note d'un prestataire (F5.5) :
 *   - les avis sur ses **ressources** : véhicules (Mobility) et expériences
 *     (Explore), dont `provider_id` désigne l'utilisateur prestataire ;
 *   - les avis **directs** sur le prestataire lui-même (`reviewable = Provider`),
 *     déposés par le client d'une mission terminée.
 *
 * Les nuitées (Stay), détenues par un propriétaire et non par un prestataire au
 * sens du module Pro, restent exclues de l'agrégation. Cette même requête sert de
 * source unique de vérité à l'écran « Avis reçus » : la note affichée en tête et
 * la liste des avis ne peuvent pas diverger.
 */
class RatingAggregator
{
    /**
     * Classes de ressources rattachées à un prestataire (via `provider_id` = user).
     *
     * @var list<class-string>
     */
    private const PROVIDER_OWNED = [
        Vehicle::class,
        TourismExperience::class,
    ];

    /**
     * Recalcule la note d'un prestataire à partir de la ressource qui vient
     * d'être notée. Sans effet si la ressource n'appartient pas à un prestataire.
     */
    public function syncFromReviewable(Model $reviewable): void
    {
        $providerUserId = $this->providerUserIdFor($reviewable);

        if ($providerUserId !== null) {
            $this->recomputeForProviderUser($providerUserId);
        }
    }

    /**
     * Recalcule `rating_avg`/`rating_count` du prestataire (identifié par son
     * user) sur l'ensemble de ses avis publiés (ressources + avis directs).
     */
    public function recomputeForProviderUser(int $providerUserId): void
    {
        $provider = Provider::where('user_id', $providerUserId)->first();

        if ($provider === null) {
            return; // le user n'est pas (ou plus) un prestataire enregistré
        }

        $query = $this->receivedReviewsQuery($provider);

        $count = (clone $query)->count();
        $average = $count > 0 ? round((float) (clone $query)->avg('rating'), 2) : null;

        $provider->update([
            'rating_avg' => $average,
            'rating_count' => $count,
        ]);
    }

    /**
     * Requête des avis **publiés** reçus par un prestataire, toutes sources
     * confondues : ses véhicules, ses expériences et les avis directs le notant.
     *
     * Source unique partagée par l'agrégat de note et l'écran « Avis reçus » (F5.5)
     * pour garantir que la note affichée et la liste restent cohérentes.
     */
    public function receivedReviewsQuery(Provider $provider): Builder
    {
        $vehicleIds = Vehicle::where('provider_id', $provider->user_id)->pluck('id');
        $experienceIds = TourismExperience::where('provider_id', $provider->user_id)->pluck('id');

        return Review::published()->where(function ($q) use ($vehicleIds, $experienceIds, $provider) {
            $q->where(function ($w) use ($vehicleIds) {
                $w->where('reviewable_type', Vehicle::class)->whereIn('reviewable_id', $vehicleIds);
            })->orWhere(function ($w) use ($experienceIds) {
                $w->where('reviewable_type', TourismExperience::class)->whereIn('reviewable_id', $experienceIds);
            })->orWhere(function ($w) use ($provider) {
                $w->where('reviewable_type', Provider::class)->where('reviewable_id', $provider->id);
            });
        });
    }

    /**
     * L'utilisateur prestataire concerné par la ressource notée, ou null si elle
     * n'est rattachée à aucun prestataire. Deux cas : la ressource détenue par un
     * prestataire (véhicule/expérience → `provider_id`), ou le prestataire noté
     * directement (`Provider` → son `user_id`).
     */
    private function providerUserIdFor(Model $reviewable): ?int
    {
        if ($reviewable instanceof Provider) {
            return $reviewable->user_id;
        }

        if (in_array($reviewable::class, self::PROVIDER_OWNED, true)) {
            return $reviewable->provider_id;
        }

        return null;
    }
}
