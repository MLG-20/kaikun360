<?php

namespace App\Modules\Mobility\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Mobility\Enums\VehicleStatus;
use App\Modules\Mobility\Events\VehicleValidated;
use App\Modules\Mobility\Http\Resources\VehicleResource;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Mobility\Services\VehicleComplianceChecker;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Validation des véhicules par les agents (phase B7.3).
 *
 * Réservé à la permission `valider:vehicule`. La validation est BLOQUÉE tant que
 * les champs de conformité obligatoires (selon la catégorie) sont absents.
 */
class VehicleValidationController extends Controller
{
    /**
     * Valide et publie un véhicule. PATCH /api/v1/vehicles/{vehicle}/approve
     */
    public function approve(Request $request, Vehicle $vehicle, VehicleComplianceChecker $checker): JsonResponse
    {
        // Conformité obligatoire : on refuse la validation si des champs manquent.
        $missing = $checker->missingFields($vehicle);
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'compliance' => ['Conformité incomplète, champs manquants : '.implode(', ', $missing).'.'],
            ]);
        }

        $vehicle->update([
            'status' => VehicleStatus::PUBLIE->value,
            'approved_by' => $request->user()->id,
            'published_at' => now(),
        ]);

        activity()->causedBy($request->user())->performedOn($vehicle)->log('Validation de véhicule');

        VehicleValidated::dispatch($vehicle);

        return ApiResponse::success(['vehicle' => VehicleResource::make($vehicle->fresh())]);
    }

    /**
     * Rejette un véhicule (motif facultatif). PATCH /api/v1/vehicles/{vehicle}/reject
     */
    public function reject(Request $request, Vehicle $vehicle): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $vehicle->update(['status' => VehicleStatus::REJETE->value]);

        activity()
            ->causedBy($request->user())
            ->performedOn($vehicle)
            ->withProperties(['reason' => $data['reason'] ?? null])
            ->log('Rejet de véhicule');

        return ApiResponse::success(['vehicle' => VehicleResource::make($vehicle->fresh())]);
    }
}
