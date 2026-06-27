<?php

namespace App\Modules\Explore\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Explore\Enums\ExperienceStatus;
use App\Modules\Explore\Http\Resources\ExperienceResource;
use App\Modules\Explore\Models\TourismExperience;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Validation des expériences par les agents (phase B6.2).
 *
 * Réservé à la permission `valider:experience` (agent, admin, super_admin) —
 * appliquée par le middleware `can:valider:experience` sur les routes.
 */
class ExperienceValidationController extends Controller
{
    /**
     * Valide et publie une expérience. PATCH /api/v1/experiences/{experience}/approve
     */
    public function approve(Request $request, TourismExperience $experience): JsonResponse
    {
        $experience->update([
            'status' => ExperienceStatus::PUBLIE->value,
            'approved_by' => $request->user()->id,
            'published_at' => now(),
        ]);

        activity()->causedBy($request->user())->performedOn($experience)->log('Validation d\'expérience');

        return ApiResponse::success(['experience' => ExperienceResource::make($experience->fresh())]);
    }

    /**
     * Rejette une expérience (motif facultatif). PATCH /api/v1/experiences/{experience}/reject
     */
    public function reject(Request $request, TourismExperience $experience): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $experience->update(['status' => ExperienceStatus::REJETE->value]);

        activity()
            ->causedBy($request->user())
            ->performedOn($experience)
            ->withProperties(['reason' => $data['reason'] ?? null])
            ->log('Rejet d\'expérience');

        return ApiResponse::success(['experience' => ExperienceResource::make($experience->fresh())]);
    }
}
