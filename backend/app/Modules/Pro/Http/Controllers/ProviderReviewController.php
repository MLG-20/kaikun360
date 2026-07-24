<?php

namespace App\Modules\Pro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pro\Http\Resources\ProviderReviewResource;
use App\Modules\Pro\Models\Provider;
use App\Services\RatingAggregator;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * « Avis reçus » — consultation par le prestataire des avis publiés qui le
 * concernent (module Pro, espace connecté F5.5).
 *
 * Deux sources d'avis sont réunies (cf. `RatingAggregator`) : les avis sur ses
 * ressources (véhicules, expériences) et les avis **directs** déposés après une
 * mission terminée. La note de tête (moyenne, total, répartition par étoiles) est
 * calculée **en direct** sur la même requête que la liste, si bien qu'elles ne
 * peuvent jamais diverger.
 *
 * Tout est scopé au **profil prestataire du compte connecté** (404 s'il n'en a
 * pas), comme les autres écrans de l'espace prestataire.
 */
class ProviderReviewController extends Controller
{
    /**
     * Le profil prestataire du compte connecté (404 sinon).
     */
    private function providerFor(Request $request): Provider
    {
        return Provider::where('user_id', $request->user()->id)->firstOrFail();
    }

    /**
     * Liste des avis reçus + synthèse de notation. GET /api/v1/providers/reviews
     */
    public function index(Request $request, RatingAggregator $aggregator): JsonResponse
    {
        $provider = $this->providerFor($request);

        // Avis publiés reçus (ressources + avis directs), auteur et ressource
        // notée préchargés pour le libellé de source, plus récents d'abord.
        $reviews = $aggregator->receivedReviewsQuery($provider)
            ->with(['author', 'reviewable'])
            ->latest()
            ->get();

        $count = $reviews->count();
        $average = $count > 0 ? round((float) $reviews->avg('rating'), 2) : null;

        // Répartition 5★ → 1★ (toujours les cinq clés, même à zéro) pour l'histogramme.
        $distribution = [];
        for ($star = 5; $star >= 1; $star--) {
            $distribution[$star] = $reviews->where('rating', $star)->count();
        }

        return ApiResponse::success([
            'summary' => [
                'average' => $average,
                'count' => $count,
                'distribution' => $distribution,
            ],
            'reviews' => ProviderReviewResource::collection($reviews),
        ]);
    }
}
