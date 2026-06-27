<?php

namespace App\Modules\Explore\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Explore\Enums\ExperienceStatus;
use App\Modules\Explore\Http\Requests\StoreExperienceRequest;
use App\Modules\Explore\Http\Resources\ExperienceResource;
use App\Modules\Explore\Models\TourismExperience;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

/**
 * Publication et suivi des expériences par les PRESTATAIRES (phase B6.2).
 *
 * La publication est réservée aux prestataires vérifiés (policy `create`). Toute
 * expérience créée part « en attente de validation » : elle n'apparaît au
 * catalogue qu'après approbation d'un agent.
 */
class ExperienceManagementController extends Controller
{
    /**
     * Publie une expérience (en attente de validation). POST /api/v1/experiences
     */
    public function store(StoreExperienceRequest $request): JsonResponse
    {
        $experience = TourismExperience::create($request->validated() + [
            'reference' => 'EXP-'.Str::upper(Str::random(8)),
            'provider_id' => $request->user()->id,
            'status' => ExperienceStatus::EN_ATTENTE_VALIDATION->value,
        ]);

        return ApiResponse::created(['experience' => ExperienceResource::make($experience)]);
    }

    /**
     * Mes expériences (tous statuts). GET /api/v1/experiences/mine
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        $experiences = TourismExperience::where('provider_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return ExperienceResource::collection($experiences);
    }
}
