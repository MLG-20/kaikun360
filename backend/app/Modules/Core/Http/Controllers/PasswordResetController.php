<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Services\VerificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Récupération de compte — mot de passe oublié (phase B1.4).
 *
 * Endpoints PUBLICS : l'utilisateur n'est pas connecté (il a justement perdu
 * l'accès). On identifie le compte par e-mail OU téléphone. Pour ne pas
 * faciliter l'énumération de comptes, `forgot` répond TOUJOURS de la même façon.
 *
 * Note "téléphone oublié" : un utilisateur qui ne se souvient plus de son
 * téléphone peut toujours passer par son e-mail (et inversement) ; les deux
 * sont des identifiants acceptés ici.
 */
class PasswordResetController extends Controller
{
    public function __construct(private readonly VerificationService $verification) {}

    /**
     * Demande de réinitialisation : envoie un code. POST /api/v1/auth/password/forgot
     */
    public function forgot(Request $request): JsonResponse
    {
        $data = $request->validate(
            ['login' => ['required', 'string']],
            ['login.required' => "L'identifiant (e-mail ou téléphone) est obligatoire."]
        );

        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user = User::where($field, $data['login'])->first();

        // On n'envoie un code que si le compte existe, mais la réponse est
        // identique dans tous les cas (anti-énumération de comptes).
        if ($user) {
            $this->verification->issue($user, VerificationService::PURPOSE_PASSWORD_RESET, $field);
        }

        return ApiResponse::success([
            'message' => 'Si un compte correspond à cet identifiant, un code de réinitialisation a été envoyé.',
        ]);
    }

    /**
     * Réinitialisation effective avec le code reçu. POST /api/v1/auth/password/reset
     */
    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'code' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'login.required' => "L'identifiant est obligatoire.",
            'code.required' => 'Le code est obligatoire.',
            'password.required' => 'Le nouveau mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ]);

        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user = User::where($field, $data['login'])->first();

        if (! $user || ! $this->verification->verify($user, VerificationService::PURPOSE_PASSWORD_RESET, $field, $data['code'])) {
            return ApiResponse::error('Code invalide ou expiré.', 422);
        }

        $user->password = $data['password']; // haché automatiquement (cast 'hashed')
        $user->save();

        // Sécurité : on révoque tous les tokens existants (toutes les sessions),
        // afin de couper un éventuel accès frauduleux après une reprise en main.
        $user->tokens()->delete();

        activity()->causedBy($user)->performedOn($user)->log('Réinitialisation du mot de passe');

        return ApiResponse::success([
            'message' => 'Mot de passe réinitialisé avec succès. Veuillez vous reconnecter.',
        ]);
    }
}
