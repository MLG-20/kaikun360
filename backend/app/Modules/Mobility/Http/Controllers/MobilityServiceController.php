<?php

namespace App\Modules\Mobility\Http\Controllers;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Modules\Mobility\Enums\MobilityServiceType;
use App\Modules\Mobility\Http\Resources\MobilityServiceResource;
use App\Modules\Mobility\Models\MobilityService;
use App\Support\ApiResponse;
use App\Support\Cache\CatalogCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Recherche PUBLIQUE des services de mobilité (phase B7.3).
 *
 * Recherche par type / ville (départ ou destination) / date de départ. Seuls les
 * services PUBLIÉS apparaissent.
 */
class MobilityServiceController extends Controller
{
    /**
     * Recherche filtrable et paginée. GET /api/v1/mobility-services
     *
     * Résultat mis en cache par jeu de filtres + page (B17.2). Invalidé dès
     * qu'un service change (voir MobilityService::booted()).
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'type' => ['sometimes', Rule::in(MobilityServiceType::values())],
            'departure' => ['sometimes', 'string', 'max:255'],
            'destination' => ['sometimes', 'string', 'max:255'],
            'date' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $cacheParams = $filters + ['page' => $request->integer('page', 1)];

        $payload = CatalogCache::remember('mobility', $cacheParams, function () use ($filters) {
            $query = MobilityService::query()->published();

            $query->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v));
            $query->when($filters['departure'] ?? null, fn ($q, $v) => $q->where('departure', $v));
            $query->when($filters['destination'] ?? null, fn ($q, $v) => $q->where('destination', $v));
            // Recherche par date : services dont le départ tombe le jour demandé.
            $query->when($filters['date'] ?? null, fn ($q, $v) => $q->whereDate('departure_at', $v));

            $query->orderBy('departure_at');

            return MobilityServiceResource::collection(
                $query->paginate($filters['per_page'] ?? 15)->withQueryString()
            )->response()->getData(true);
        });

        return response()->json($payload);
    }

    /**
     * Fiche d'un départ programmé. GET /api/v1/mobility-services/{id}
     *
     * ⚠️ **Cet endpoint n'existait pas** (F8.10). Le module n'exposait que la
     * recherche : un trajet ne pouvait donc pas avoir de page, et la
     * réservation d'une place passait forcément par un conseiller sur WhatsApp
     * — alors que `POST /mobility-services/{id}/bookings` était livré depuis
     * B7.3 et n'avait aucun appelant.
     *
     * Public, comme la recherche : on consulte un départ sans être connecté ;
     * c'est réserver qui demande un compte vérifié.
     *
     * Le **remplissage** est joint à la fiche. Sans lui, l'écran afficherait
     * une capacité totale — « 30 places » — pour un départ où il n'en reste
     * qu'une, et le refus n'arriverait qu'après le clic.
     */
    public function show(string $id): JsonResponse
    {
        $service = MobilityService::query()->published()->findOrFail($id);

        // Places prises = participants des réservations non annulées. Même
        // calcul que le contrôleur de réservation, qui reste seul juge au
        // moment d'écrire : ceci n'est qu'un affichage, deux clients peuvent
        // toujours viser la dernière place en même temps.
        $prises = (int) $service->bookings()
            ->whereNotIn('status', $this->statutsAnnules())
            ->sum('guests');

        return ApiResponse::success([
            'mobility_service' => MobilityServiceResource::make($service),
            'seats_taken' => $prises,
            'seats_left' => max(0, $service->capacity - $prises),
        ]);
    }

    /**
     * Statuts d'annulation, DÉRIVÉS de l'enum : une réservation annulée rend
     * sa place au départ.
     *
     * @return array<int, string>
     */
    private function statutsAnnules(): array
    {
        return array_map(
            fn (BookingStatus $statut) => $statut->value,
            array_filter(BookingStatus::cases(), fn (BookingStatus $statut) => $statut->estAnnulee()),
        );
    }
}
