<?php

namespace App\Modules\Pro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pro\Http\Requests\StoreCertificationRequest;
use App\Modules\Pro\Http\Requests\UpdateProviderProfileRequest;
use App\Modules\Pro\Http\Resources\ProviderCertificationResource;
use App\Modules\Pro\Http\Resources\ProviderResource;
use App\Modules\Pro\Models\Provider;
use App\Modules\Pro\Models\ProviderCertification;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * « Mes services » — édition du profil prestataire et de ses documents de
 * certification par le prestataire lui-même (module Pro, espace connecté F5).
 *
 * Tout est scopé au **profil prestataire du compte connecté** (404 s'il n'en a
 * pas). L'inscription initiale reste `ProviderRegistrationController@store` ; ce
 * contrôleur porte la mise à jour ultérieure.
 *
 * ⚠️ L'édition du profil **ne modifie pas** le statut de validation : corriger
 * une description ne doit pas re-déclencher une revue back-office. De même, une
 * certification ajoutée ici est toujours « non vérifiée » (la vérification est
 * une action agent, cf. `ProviderValidationController`).
 */
class ProviderProfileController extends Controller
{
    /**
     * Le profil prestataire du compte connecté (404 sinon).
     */
    private function providerFor(Request $request): Provider
    {
        return Provider::where('user_id', $request->user()->id)->firstOrFail();
    }

    /**
     * Met à jour mon profil prestataire. PUT /api/v1/providers/mine
     *
     * Champs éditables : raison sociale, catégorie, présentation. Le statut, la
     * notation et les certifications ne passent pas par ici.
     */
    public function update(UpdateProviderProfileRequest $request): JsonResponse
    {
        $provider = $this->providerFor($request);

        $provider->update($request->validated());

        return ApiResponse::success([
            'provider' => ProviderResource::make($provider->load('certifications')),
        ]);
    }

    /**
     * Ajoute une certification. POST /api/v1/providers/certifications
     *
     * Créée « non vérifiée » (le champ `verified` a false par défaut) : la
     * vérification relève du back-office.
     */
    public function storeCertification(StoreCertificationRequest $request): JsonResponse
    {
        $provider = $this->providerFor($request);

        // `verified => false` explicite : le défaut DB ne s'applique pas à
        // l'instance renvoyée (sinon la Resource sérialiserait `verified: null`).
        $certification = $provider->certifications()->create([
            ...$request->validated(),
            'verified' => false,
        ]);

        return ApiResponse::created([
            'certification' => ProviderCertificationResource::make($certification),
        ]);
    }

    /**
     * Supprime une certification.
     * DELETE /api/v1/providers/certifications/{certification}
     */
    public function destroyCertification(
        Request $request,
        ProviderCertification $certification,
    ): JsonResponse {
        $provider = $this->providerFor($request);

        // Cloisonnement : on ne supprime que ses propres certifications.
        abort_unless($certification->provider_id === $provider->id, 404);

        $certification->delete();

        return ApiResponse::success(['deleted' => true]);
    }
}
