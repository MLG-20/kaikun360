<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Requests\UpdateProfileRequest;
use App\Modules\Core\Http\Resources\UserResource;
use App\Modules\Core\Services\AccountAnonymizer;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Gestion du compte de l'utilisateur connecté (phase B1.5).
 *
 * Tous les endpoints sont auto-restreints à l'utilisateur authentifié
 * (request->user()) : aucun risque d'accès aux données d'autrui.
 */
class UserController extends Controller
{
    /**
     * Profil de l'utilisateur connecté. GET /api/v1/users/me
     */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'user' => UserResource::make($request->user()->load('profile')),
        ]);
    }

    /**
     * Mise à jour du profil. PATCH /api/v1/users/me
     *
     * Met à jour les champs de l'utilisateur (nom, ville) et les préférences
     * du profil, de façon atomique.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        DB::transaction(function () use ($user, $data) {
            // On n'applique que les champs réellement transmis (mise à jour partielle).
            if (array_key_exists('name', $data)) {
                $user->name = $data['name'];
            }
            if (array_key_exists('city', $data)) {
                $user->city = $data['city'];
            }
            $user->save();

            if (array_key_exists('preferences', $data)) {
                $user->profile()->update(['preferences' => $data['preferences']]);
            }
        });

        return ApiResponse::success([
            'user' => UserResource::make($user->fresh()->load('profile')),
        ]);
    }

    /**
     * Suppression du compte sur demande (RGPD, B15.4). DELETE /api/v1/users/me
     *
     * Le compte est ANONYMISÉ (données personnelles scrubées, accès coupés) et
     * non effacé physiquement : réservations et paiements sont conservés pour la
     * durée de rétention comptable/légale.
     */
    public function destroy(Request $request, AccountAnonymizer $anonymizer): JsonResponse
    {
        $anonymizer->anonymize($request->user());

        return ApiResponse::success(['message' => 'Compte anonymisé et désactivé.']);
    }
}
