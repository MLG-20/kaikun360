<?php

namespace App\Modules\Mobility\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Mobility\Enums\MobilityServiceType;
use App\Modules\Mobility\Http\Resources\MobilityServiceResource;
use App\Modules\Mobility\Models\MobilityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'type' => ['sometimes', Rule::in(MobilityServiceType::values())],
            'departure' => ['sometimes', 'string', 'max:255'],
            'destination' => ['sometimes', 'string', 'max:255'],
            'date' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $query = MobilityService::query()->published();

        $query->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v));
        $query->when($filters['departure'] ?? null, fn ($q, $v) => $q->where('departure', $v));
        $query->when($filters['destination'] ?? null, fn ($q, $v) => $q->where('destination', $v));
        // Recherche par date : services dont le départ tombe le jour demandé.
        $query->when($filters['date'] ?? null, fn ($q, $v) => $q->whereDate('departure_at', $v));

        $query->orderBy('departure_at');

        return MobilityServiceResource::collection(
            $query->paginate($filters['per_page'] ?? 15)->withQueryString()
        );
    }
}
