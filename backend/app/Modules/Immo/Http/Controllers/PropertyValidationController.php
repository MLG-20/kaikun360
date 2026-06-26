<?php

namespace App\Modules\Immo\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Immo\Enums\PropertyStatus;
use App\Modules\Immo\Events\PropertyValidated;
use App\Modules\Immo\Http\Resources\PropertyResource;
use App\Modules\Immo\Models\Property;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Validation des biens par les agents (phase B2.4).
 *
 * Réservé aux utilisateurs ayant la permission `valider:bien` (agent, admin,
 * super_admin) — appliquée par le middleware `can:valider:bien` sur les routes.
 */
class PropertyValidationController extends Controller
{
    /**
     * Valide et publie un bien. PATCH /api/v1/properties/{property}/approve
     */
    public function approve(Request $request, Property $property): JsonResponse
    {
        $property->update([
            'status' => PropertyStatus::PUBLIE->value,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'published_at' => now(),
        ]);

        // Audit d'une action sensible (validation de bien — cf. B15).
        activity()->causedBy($request->user())->performedOn($property)->log('Validation de bien');

        // Notifie le propriétaire de la publication.
        PropertyValidated::dispatch($property);

        return ApiResponse::success([
            'property' => PropertyResource::make($property->fresh()->load(['region', 'department', 'commune', 'owner'])),
        ]);
    }

    /**
     * Rejette un bien (avec motif facultatif). PATCH /api/v1/properties/{property}/reject
     */
    public function reject(Request $request, Property $property): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $property->update(['status' => PropertyStatus::REJETE->value]);

        activity()
            ->causedBy($request->user())
            ->performedOn($property)
            ->withProperties(['reason' => $data['reason'] ?? null])
            ->log('Rejet de bien');

        return ApiResponse::success([
            'property' => PropertyResource::make($property->fresh()->load(['region', 'department', 'commune', 'owner'])),
        ]);
    }
}
