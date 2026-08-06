<?php

namespace App\Modules\Mobility\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Mobility\Enums\VehicleStatus;
use App\Modules\Mobility\Events\VehicleCreated;
use App\Modules\Mobility\Http\Requests\StoreVehicleRequest;
use App\Modules\Mobility\Http\Requests\UpdateVehicleRequest;
use App\Modules\Mobility\Http\Resources\VehicleResource;
use App\Modules\Mobility\Models\Vehicle;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Publication et gestion des véhicules par les PRESTATAIRES (phase B7.3).
 *
 * La publication est réservée aux prestataires vérifiés (policy `create`). Tout
 * véhicule créé part « en attente de validation » et émet `VehicleCreated`
 * (mise en file de validation). La modification est réservée au propriétaire.
 */
class VehicleManagementController extends Controller
{
    /**
     * Dépose un véhicule. POST /api/v1/vehicles
     */
    public function store(StoreVehicleRequest $request): JsonResponse
    {
        $vehicle = Vehicle::create($request->validated() + [
            'reference' => 'VEH-'.Str::upper(Str::random(8)),
            'provider_id' => $request->user()->id,
            'status' => VehicleStatus::EN_ATTENTE_VALIDATION->value,
        ]);

        VehicleCreated::dispatch($vehicle);

        return ApiResponse::created(['vehicle' => VehicleResource::make($vehicle)]);
    }

    /**
     * Mes véhicules (tous statuts). GET /api/v1/vehicles/mine
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        // F8.18 — les photos déjà déposées voyagent avec la liste : c'est
        // depuis elle que le formulaire d'édition du prestataire se préremplit,
        // et sans elles il afficherait « aucune photo » sur un véhicule illustré.
        $vehicles = Vehicle::where('provider_id', $request->user()->id)
            ->with('media')
            ->latest()
            ->paginate(15);

        return VehicleResource::collection($vehicles);
    }

    /**
     * Met à jour un véhicule. PATCH /api/v1/vehicles/{vehicle}
     *
     * Réservé au prestataire propriétaire (policy `update`). Le statut n'est pas
     * modifiable ici.
     */
    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): JsonResponse
    {
        Gate::authorize('update', $vehicle);

        $vehicle->update($request->validated());

        return ApiResponse::success(['vehicle' => VehicleResource::make($vehicle->fresh())]);
    }
}
