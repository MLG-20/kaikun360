<?php

namespace App\Modules\Pro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Enums\ProfileType;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Models\Profile;
use App\Modules\Pro\Enums\ProviderStatus;
use App\Modules\Pro\Http\Requests\RegisterProviderRequest;
use App\Modules\Pro\Http\Resources\ProviderResource;
use App\Modules\Pro\Models\Provider;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Inscription prestataire marketplace (phase B10.2).
 */
class ProviderRegistrationController extends Controller
{
    /**
     * Crée le profil prestataire de l'utilisateur. POST /api/v1/providers
     *
     * En une transaction : rôle prestataire, profil de type prestataire, profil
     * marketplace « en attente » et certifications fournies. Le prestataire n'est
     * pas encore autorisé à publier (validation requise, B10.2).
     */
    public function store(RegisterProviderRequest $request): JsonResponse
    {
        $user = $request->user();

        if (Provider::where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'business_name' => ['Un profil prestataire existe déjà pour ce compte.'],
            ]);
        }

        $data = $request->validated();

        $provider = DB::transaction(function () use ($user, $data) {
            // Casquette prestataire (rôle + type de profil), sans écraser le KYC.
            if (! $user->hasRole(UserRole::PRESTATAIRE->value)) {
                $user->assignRole(UserRole::PRESTATAIRE->value);
            }
            Profile::updateOrCreate(
                ['user_id' => $user->id],
                ['type' => ProfileType::PRESTATAIRE->value],
            );

            $provider = Provider::create([
                'user_id' => $user->id,
                'business_name' => $data['business_name'],
                'category' => $data['category'],
                'bio' => $data['bio'] ?? null,
                'status' => ProviderStatus::EN_ATTENTE->value,
            ]);

            foreach ($data['certifications'] ?? [] as $certification) {
                $provider->certifications()->create([
                    'name' => $certification['name'],
                    'issuer' => $certification['issuer'] ?? null,
                ]);
            }

            return $provider;
        });

        return ApiResponse::created([
            'provider' => ProviderResource::make($provider->load('certifications')),
        ]);
    }

    /**
     * Mon profil prestataire. GET /api/v1/providers/mine
     */
    public function mine(Request $request): JsonResponse
    {
        $provider = Provider::where('user_id', $request->user()->id)
            ->with('certifications')
            ->firstOrFail();

        return ApiResponse::success(['provider' => ProviderResource::make($provider)]);
    }
}
