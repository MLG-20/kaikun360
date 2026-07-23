<?php

namespace App\Modules\Pro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pro\Http\Requests\StoreUnavailabilityRequest;
use App\Modules\Pro\Http\Requests\UpdateWeeklyAvailabilityRequest;
use App\Modules\Pro\Http\Resources\ProviderUnavailabilityResource;
use App\Modules\Pro\Http\Resources\ProviderWeeklyAvailabilityResource;
use App\Modules\Pro\Models\Provider;
use App\Modules\Pro\Models\ProviderUnavailability;
use App\Modules\Pro\Models\ProviderWeeklyAvailability;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Disponibilités du prestataire (module Pro, F5.4).
 *
 * Deux volets complémentaires, tous scopés au **profil prestataire du compte
 * connecté** (404 s'il n'en a pas) : le **planning hebdomadaire récurrent**
 * (horaires habituels, un jour par ligne) et les **périodes d'indisponibilité**
 * ponctuelles (congés) qui priment sur le planning.
 */
class ProviderAvailabilityController extends Controller
{
    /**
     * Le profil prestataire du compte connecté (404 sinon).
     */
    private function providerFor(Request $request): Provider
    {
        return Provider::where('user_id', $request->user()->id)->firstOrFail();
    }

    /**
     * Mes disponibilités. GET /api/v1/providers/availability
     *
     * Renvoie TOUJOURS les 7 jours (les jours non enregistrés sont « fermés »),
     * plus les périodes d'indisponibilité à venir ou en cours (les périodes
     * entièrement passées sont masquées).
     */
    public function show(Request $request): JsonResponse
    {
        $provider = $this->providerFor($request);

        return ApiResponse::success([
            'weekly' => $this->weeklyPayload($provider),
            'unavailabilities' => ProviderUnavailabilityResource::collection(
                $provider->unavailabilities()
                    ->whereDate('end_date', '>=', now()->toDateString())
                    ->orderBy('start_date')
                    ->get(),
            ),
        ]);
    }

    /**
     * Enregistre le planning hebdomadaire. PUT /api/v1/providers/availability/weekly
     *
     * Chaque jour fourni est créé ou mis à jour (clé `provider_id` + `weekday`).
     * Un jour fermé voit ses heures remises à null.
     */
    public function updateWeekly(UpdateWeeklyAvailabilityRequest $request): JsonResponse
    {
        $provider = $this->providerFor($request);

        DB::transaction(function () use ($provider, $request) {
            foreach ($request->validated('days') as $day) {
                $open = (bool) $day['is_open'];
                ProviderWeeklyAvailability::updateOrCreate(
                    ['provider_id' => $provider->id, 'weekday' => $day['weekday']],
                    [
                        'is_open' => $open,
                        'start_time' => $open ? $day['start_time'] : null,
                        'end_time' => $open ? $day['end_time'] : null,
                    ],
                );
            }
        });

        return ApiResponse::success(['weekly' => $this->weeklyPayload($provider)]);
    }

    /**
     * Ajoute une période d'indisponibilité. POST /api/v1/providers/availability/unavailability
     */
    public function storeUnavailability(StoreUnavailabilityRequest $request): JsonResponse
    {
        $provider = $this->providerFor($request);

        $period = $provider->unavailabilities()->create($request->validated());

        return ApiResponse::created([
            'unavailability' => ProviderUnavailabilityResource::make($period),
        ]);
    }

    /**
     * Supprime une période d'indisponibilité.
     * DELETE /api/v1/providers/availability/unavailability/{unavailability}
     */
    public function destroyUnavailability(
        Request $request,
        ProviderUnavailability $unavailability,
    ): JsonResponse {
        $provider = $this->providerFor($request);

        // Cloisonnement : on ne supprime que ses propres périodes.
        abort_unless($unavailability->provider_id === $provider->id, 404);

        $unavailability->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    /**
     * Construit les 7 jours du planning (jours absents = fermés), triés lundi→dimanche.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    private function weeklyPayload(Provider $provider)
    {
        $existing = $provider->weeklyAvailabilities()->get()->keyBy('weekday');

        $days = collect(range(0, 6))->map(
            fn (int $weekday) => $existing->get($weekday)
                ?? new ProviderWeeklyAvailability(['weekday' => $weekday, 'is_open' => false]),
        );

        return ProviderWeeklyAvailabilityResource::collection($days);
    }
}
