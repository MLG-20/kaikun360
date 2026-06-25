<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Enums\ProfileType;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Http\Requests\LoginRequest;
use App\Modules\Core\Http\Requests\RegisterRequest;
use App\Modules\Core\Http\Resources\UserResource;
use App\Modules\Core\Services\VerificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Contrôleur d'authentification (module Core).
 *
 * Gère l'inscription, la connexion (e-mail OU téléphone) et la déconnexion.
 * Toutes les réponses respectent l'enveloppe standard (App\Support\ApiResponse).
 */
class AuthController extends Controller
{
    public function __construct(private readonly VerificationService $verification) {}

    /**
     * Inscription d'un nouvel utilisateur. POST /api/v1/auth/register
     *
     * Crée l'utilisateur, son profil et lui attribue son rôle par défaut,
     * le tout dans UNE transaction (cohérence garantie : soit tout réussit,
     * soit rien n'est créé). Émet ensuite un token d'API.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $profileType = ProfileType::from($data['profile_type']);

        // Transaction : User + Profile + rôle créés de façon atomique.
        $user = DB::transaction(function () use ($data, $profileType) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'city' => $data['city'] ?? null,
                'password' => $data['password'], // haché automatiquement (cast 'hashed')
                // status laissé à sa valeur par défaut : en_attente_verification.
            ]);

            // Profil métier associé (1–1).
            $user->profile()->create([
                'type' => $profileType->value,
                'verification_status' => 'non_verifie',
            ]);

            // Rôle de sécurité par défaut, déduit du type de profil.
            $user->assignRole(UserRole::defaultForProfileType($profileType)->value);

            return $user;
        });

        // Journal d'audit : trace de l'inscription.
        activity()
            ->causedBy($user)
            ->performedOn($user)
            ->withProperties(['profile_type' => $profileType->value])
            ->log('Inscription');

        // Envoi automatique d'un code de vérification e-mail (le compte reste
        // en attente de vérification tant que l'utilisateur ne l'a pas saisi).
        $this->verification->issue($user, VerificationService::PURPOSE_ACCOUNT, VerificationService::CHANNEL_EMAIL);

        // Token d'API à renvoyer au frontend.
        $token = $user->createToken('auth')->plainTextToken;

        return ApiResponse::created([
            'user' => UserResource::make($user->load('profile')),
            'token' => $token,
        ]);
    }

    /**
     * Connexion. POST /api/v1/auth/login
     *
     * Le champ `login` peut être un e-mail ou un téléphone : on détecte
     * automatiquement lequel. En cas d'échec, on renvoie une erreur 422
     * générique (sans révéler si c'est l'identifiant ou le mot de passe
     * qui est faux, pour ne pas faciliter l'énumération de comptes).
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Détecte si l'identifiant fourni est un e-mail ou un téléphone.
        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $user = User::where($field, $data['login'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Identifiants invalides.'],
            ]);
        }

        $token = $user->createToken('auth')->plainTextToken;

        return ApiResponse::success([
            'user' => UserResource::make($user->load('profile')),
            'token' => $token,
        ]);
    }

    /**
     * Déconnexion. POST /api/v1/auth/logout (protégé par auth:sanctum)
     *
     * Révoque uniquement le token utilisé pour la requête courante
     * (les autres sessions/appareils de l'utilisateur restent connectés).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(['message' => 'Déconnexion réussie.']);
    }
}
