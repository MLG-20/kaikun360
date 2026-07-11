<?php

namespace App\Services;

use App\Models\Review;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Pro\Models\Provider;
use Illuminate\Database\Eloquent\Model;

/**
 * Agrégation de la notation prestataire à partir des avis (couche transversale, B12.3).
 *
 * Quand un avis est publié, on recalcule la note moyenne (`rating_avg`) et le
 * nombre d'avis (`rating_count`) du prestataire concerné, en n'agrégeant que les
 * avis **publiés** portant sur ses ressources.
 *
 * Seules les ressources détenues par un prestataire alimentent cette note :
 * véhicules (Mobility) et expériences (Explore), dont `provider_id` désigne
 * l'utilisateur prestataire. Les nuitées (Stay), détenues par un propriétaire et
 * non par un prestataire au sens du module Pro, sont exclues de l'agrégation.
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
     * user) sur l'ensemble de ses avis publiés (véhicules + expériences).
     */
    public function recomputeForProviderUser(int $providerUserId): void
    {
        $provider = Provider::where('user_id', $providerUserId)->first();

        if ($provider === null) {
            return; // le user n'est pas (ou plus) un prestataire enregistré
        }

        $vehicleIds = Vehicle::where('provider_id', $providerUserId)->pluck('id');
        $experienceIds = TourismExperience::where('provider_id', $providerUserId)->pluck('id');

        $query = Review::published()->where(function ($q) use ($vehicleIds, $experienceIds) {
            $q->where(function ($w) use ($vehicleIds) {
                $w->where('reviewable_type', Vehicle::class)->whereIn('reviewable_id', $vehicleIds);
            })->orWhere(function ($w) use ($experienceIds) {
                $w->where('reviewable_type', TourismExperience::class)->whereIn('reviewable_id', $experienceIds);
            });
        });

        $count = (clone $query)->count();
        $average = $count > 0 ? round((float) (clone $query)->avg('rating'), 2) : null;

        $provider->update([
            'rating_avg' => $average,
            'rating_count' => $count,
        ]);
    }

    /**
     * L'utilisateur prestataire propriétaire de la ressource, ou null si la
     * ressource n'est pas rattachée à un prestataire.
     */
    private function providerUserIdFor(Model $reviewable): ?int
    {
        if (in_array($reviewable::class, self::PROVIDER_OWNED, true)) {
            return $reviewable->provider_id;
        }

        return null;
    }
}
