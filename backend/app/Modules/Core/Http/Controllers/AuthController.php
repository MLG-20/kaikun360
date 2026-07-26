<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Enums\ProfileType;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Http\Requests\LoginRequest;
use App\Modules\Core\Http\Requests\RegisterRequest;
use App\Modules\Core\Http\Resources\UserResource;
use App\Modules\Core\Services\VerificationService;
use App\Support\ApiResponse;
use App\Support\Auth\GoogleTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Contrôleur d'authentification (module Core).
 *
 * Gère l'inscription, la connexion (e-mail OU téléphone) et la déconnexion.
 * Toutes les réponses respectent l'enveloppe standard (App\Support\ApiResponse).
 */
class AuthController extends Controller
{
    /**
     * Durée de vie d'un jeton de session BACK-OFFICE (F7.1.d), en minutes.
     *
     * Session volontairement courte (8 h ≈ une journée de travail) : les comptes
     * privilégiés se re-authentifient régulièrement (2FA comprise). Les jetons des
     * comptes publics gardent la durée par défaut (illimitée, cf. config/sanctum).
     */
    private const BACKOFFICE_TOKEN_TTL_MINUTES = 480;

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
     * Connexion via Google. POST /api/v1/auth/google
     *
     * Le frontend obtient un ID token via Google Identity Services et l'envoie
     * ici. On le vérifie (signature + audience via {@see GoogleTokenVerifier}),
     * puis on retrouve l'utilisateur par `google_id` ou e-mail — sinon on le crée
     * en compte **client** (décision produit), e-mail déjà vérifié par Google donc
     * compte directement actif. Renvoie le même `{ user, token }` que le login.
     */
    public function google(Request $request, GoogleTokenVerifier $verifier): JsonResponse
    {
        $data = $request->validate(['id_token' => ['required', 'string']]);

        $googleUser = $verifier->verify($data['id_token']);
        if ($googleUser === null) {
            return ApiResponse::error('Jeton Google invalide.', 401);
        }

        $user = User::where('google_id', $googleUser->sub)
            ->orWhere('email', $googleUser->email)
            ->first();

        if ($user !== null) {
            // Compte existant (créé par e-mail auparavant) : on le lie à Google.
            if ($user->google_id === null) {
                $user->update(['google_id' => $googleUser->sub]);
            }
        } else {
            // Nouveau compte Google → profil client, actif (e-mail vérifié par Google).
            $user = DB::transaction(function () use ($googleUser) {
                $user = User::create([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'google_id' => $googleUser->sub,
                    'password' => Str::password(32), // aléatoire, jamais utilisé (login Google)
                    'status' => UserStatus::ACTIF->value,
                    'email_verified_at' => now(),
                ]);

                $user->profile()->create([
                    'type' => ProfileType::CLIENT->value,
                    'verification_status' => 'non_verifie',
                ]);

                $user->assignRole(UserRole::defaultForProfileType(ProfileType::CLIENT)->value);

                return $user;
            });

            activity()->causedBy($user)->performedOn($user)
                ->withProperties(['via' => 'google'])
                ->log('Inscription');
        }

        $token = $user->createToken('auth')->plainTextToken;

        return ApiResponse::success([
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

        // F7.1.d — Double authentification obligatoire pour les comptes à fort
        // privilège (admin / super_admin). On NE délivre PAS de jeton ici : on
        // envoie un code par e-mail et on renvoie un « défi » que le frontend
        // devra résoudre via POST /auth/two-factor.
        if ($user->hasAnyRole(UserRole::twoFactorRequired())) {
            $this->verification->issue($user, VerificationService::PURPOSE_TWO_FACTOR, VerificationService::CHANNEL_EMAIL);

            return ApiResponse::success([
                'two_factor_required' => true,
                'channel' => VerificationService::CHANNEL_EMAIL,
                // Identifiant à renvoyer tel quel à l'étape de vérification.
                'login' => $data['login'],
            ]);
        }

        $token = $user->createToken('auth')->plainTextToken;

        return ApiResponse::success([
            'user' => UserResource::make($user->load('profile')),
            'token' => $token,
        ]);
    }

    /**
     * Second facteur du back-office. POST /api/v1/auth/two-factor
     *
     * Résout le défi de double authentification : on vérifie le code e-mail envoyé
     * à la connexion d'un compte admin / super_admin, puis on délivre le jeton
     * d'API — avec une **expiration courte** (session back-office). Réponses
     * volontairement génériques (pas de distinction identifiant/code) pour ne pas
     * faciliter l'énumération.
     */
    public function twoFactor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user = User::where($field, $data['login'])->first();

        // Le compte doit exister, être soumis à la 2FA, et fournir un code valide.
        $ok = $user
            && $user->hasAnyRole(UserRole::twoFactorRequired())
            && $this->verification->verify($user, VerificationService::PURPOSE_TWO_FACTOR, VerificationService::CHANNEL_EMAIL, $data['code']);

        if (! $ok) {
            throw ValidationException::withMessages([
                'code' => ['Code de vérification invalide ou expiré.'],
            ]);
        }

        // Jeton à durée de vie courte (session back-office).
        $token = $user->createToken(
            'back-office',
            ['*'],
            now()->addMinutes(self::BACKOFFICE_TOKEN_TTL_MINUTES),
        )->plainTextToken;

        activity()->causedBy($user)->performedOn($user)->log('Connexion back-office (2FA validée)');

        return ApiResponse::success([
            'user' => UserResource::make($user->load('profile')),
            'token' => $token,
            'expires_at' => now()->addMinutes(self::BACKOFFICE_TOKEN_TTL_MINUTES),
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
