<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Enums\ProfileType;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Http\Resources\UserResource;
use App\Modules\Core\Notifications\WelcomeNotification;
use App\Modules\Core\Services\VerificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Vérification de compte (e-mail / téléphone) — phase B1.4.
 *
 * Endpoints protégés par auth:sanctum : l'utilisateur est déjà identifié
 * (il dispose du token reçu à l'inscription).
 */
class VerificationController extends Controller
{
    public function __construct(private readonly VerificationService $verification) {}

    /**
     * (Re)envoie un code de vérification sur le canal demandé.
     * POST /api/v1/auth/verify/send
     */
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in([VerificationService::CHANNEL_EMAIL, VerificationService::CHANNEL_PHONE])],
        ], [
            'channel.required' => 'Le canal est obligatoire.',
            'channel.in' => 'Le canal doit être "email" ou "phone".',
        ]);

        $user = $request->user();

        // On ne peut pas envoyer un SMS si aucun téléphone n'est enregistré.
        if ($data['channel'] === VerificationService::CHANNEL_PHONE && ! $user->phone) {
            return ApiResponse::error('Aucun numéro de téléphone enregistré sur ce compte.', 422);
        }

        $this->verification->issue($user, VerificationService::PURPOSE_ACCOUNT, $data['channel']);

        return ApiResponse::success(['message' => 'Code de vérification envoyé.']);
    }

    /**
     * Vérifie le code saisi par l'utilisateur et active le compte.
     * POST /api/v1/auth/verify
     */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in([VerificationService::CHANNEL_EMAIL, VerificationService::CHANNEL_PHONE])],
            'code' => ['required', 'string'],
        ], [
            'channel.required' => 'Le canal est obligatoire.',
            'channel.in' => 'Le canal doit être "email" ou "phone".',
            'code.required' => 'Le code est obligatoire.',
        ]);

        $user = $request->user();

        if (! $this->verification->verify($user, VerificationService::PURPOSE_ACCOUNT, $data['channel'], $data['code'])) {
            return ApiResponse::error('Code invalide ou expiré.', 422);
        }

        // Marque le canal concerné comme vérifié.
        $column = $data['channel'] === VerificationService::CHANNEL_EMAIL ? 'email_verified_at' : 'phone_verified_at';
        $user->{$column} = now();

        // Le compte devient actif dès que l'e-mail (canal principal) est vérifié.
        // On mémorise ce basculement : c'est LUI qui déclenche l'e-mail de
        // bienvenue, et il ne se produit qu'une fois dans la vie du compte.
        $justActivated = false;
        if ($user->email_verified_at && $user->status === UserStatus::EN_ATTENTE_VERIFICATION) {
            $user->status = UserStatus::ACTIF;
            $justActivated = true;
        }

        $user->save();

        // Accueil personnalisé selon le profil, envoyé APRÈS l'activation (et
        // donc jamais en même temps que le code de vérification).
        if ($justActivated) {
            $user->notify(new WelcomeNotification($user->profile?->type ?? ProfileType::CLIENT));
        }

        activity()->causedBy($user)->performedOn($user)->log("Vérification du canal {$data['channel']}");

        return ApiResponse::success(['user' => UserResource::make($user->load('profile'))->withPermissions()]);
    }
}
