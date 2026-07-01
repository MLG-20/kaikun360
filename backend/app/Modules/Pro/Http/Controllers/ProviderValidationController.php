<?php

namespace App\Modules\Pro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pro\Http\Requests\SanctionProviderRequest;
use App\Modules\Pro\Http\Resources\ProviderResource;
use App\Modules\Pro\Models\Provider;
use App\Modules\Pro\Services\ProviderValidationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Validation et charte qualité des prestataires par les agents (phase B10.2).
 *
 * Réservé à la permission `valider:prestataire`. Valider un prestataire débloque
 * sa publication (synchronisation du profil) ; le suspendre/refuser la bloque.
 */
class ProviderValidationController extends Controller
{
    public function __construct(private readonly ProviderValidationService $validation)
    {
    }

    /**
     * Valide un prestataire. PATCH /api/v1/providers/{provider}/validate
     */
    public function validate(Request $request, Provider $provider): JsonResponse
    {
        $provider = $this->validation->validate($provider, $request->user());

        activity()->causedBy($request->user())->performedOn($provider)->log('Validation de prestataire');

        return ApiResponse::success(['provider' => ProviderResource::make($provider)]);
    }

    /**
     * Refuse un prestataire. PATCH /api/v1/providers/{provider}/reject
     */
    public function reject(Request $request, Provider $provider): JsonResponse
    {
        $provider = $this->validation->reject($provider);

        activity()->causedBy($request->user())->performedOn($provider)->log('Refus de prestataire');

        return ApiResponse::success(['provider' => ProviderResource::make($provider)]);
    }

    /**
     * Suspend un prestataire (motif obligatoire). PATCH /api/v1/providers/{provider}/suspend
     */
    public function suspend(SanctionProviderRequest $request, Provider $provider): JsonResponse
    {
        $provider = $this->validation->suspend($provider, $request->validated()['reason']);

        activity()->causedBy($request->user())->performedOn($provider)
            ->withProperties(['reason' => $request->validated()['reason']])
            ->log('Suspension de prestataire');

        return ApiResponse::success(['provider' => ProviderResource::make($provider)]);
    }

    /**
     * Émet un avertissement (charte qualité). PATCH /api/v1/providers/{provider}/warn
     *
     * Au-delà du seuil d'avertissements, le prestataire est suspendu d'office.
     */
    public function warn(SanctionProviderRequest $request, Provider $provider): JsonResponse
    {
        $provider = $this->validation->warn($provider, $request->validated()['reason']);

        activity()->causedBy($request->user())->performedOn($provider)
            ->withProperties(['reason' => $request->validated()['reason'], 'warnings' => $provider->warnings_count])
            ->log('Avertissement prestataire');

        return ApiResponse::success(['provider' => ProviderResource::make($provider)]);
    }
}
