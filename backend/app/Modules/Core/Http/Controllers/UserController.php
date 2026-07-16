<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Commune;
use App\Models\Department;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Http\Requests\UpdatePasswordRequest;
use App\Modules\Core\Http\Requests\UpdateProfileRequest;
use App\Modules\Core\Http\Resources\UserResource;
use App\Modules\Core\Services\AccountAnonymizer;
use App\Modules\Core\Services\VerificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Gestion du compte de l'utilisateur connecté (phase B1.5, étendue F3.2b).
 *
 * Tous les endpoints sont auto-restreints à l'utilisateur authentifié
 * (request->user()) : aucun risque d'accès aux données d'autrui.
 */
class UserController extends Controller
{
    public function __construct(private readonly VerificationService $verification) {}

    /**
     * Profil de l'utilisateur connecté. GET /api/v1/users/me
     */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'user' => UserResource::make(
                $request->user()->load(['profile', 'region', 'department', 'commune']),
            ),
        ]);
    }

    /**
     * Mise à jour du profil. PATCH /api/v1/users/me
     *
     * Met à jour l'identité, la localisation (cascade géo + adresse) et les
     * préférences. **E-mail / téléphone (F3.2b)** : un changement remet le canal
     * concerné à « non vérifié » et **renvoie un code de vérification au nouveau
     * contact** ; la réponse signale les canaux à re-vérifier.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Un changement d'e-mail / de téléphone impose une re-vérification.
        $emailChanged = array_key_exists('email', $data) && $data['email'] !== $user->email;
        $phoneChanged = array_key_exists('phone', $data) && $data['phone'] !== $user->phone;

        DB::transaction(function () use ($user, $data, $emailChanged, $phoneChanged) {
            // Champs simples de l'utilisateur (mise à jour partielle).
            foreach (['name', 'address', 'region_id', 'department_id', 'commune_id'] as $field) {
                if (array_key_exists($field, $data)) {
                    $user->{$field} = $data[$field];
                }
            }

            // La ville (texte historique) est dérivée de la commune / du
            // département choisi quand la localisation est renseignée, pour
            // rester cohérente avec les lectures existantes (recherche, formulaires).
            if (array_key_exists('commune_id', $data) || array_key_exists('department_id', $data)) {
                $user->city = $this->deriveCity($data);
            } elseif (array_key_exists('city', $data)) {
                $user->city = $data['city'];
            }

            if ($emailChanged) {
                $user->email = $data['email'];
                $user->email_verified_at = null;
            }
            if ($phoneChanged) {
                $user->phone = $data['phone'];
                $user->phone_verified_at = null;
            }

            // Si plus aucun canal n'est vérifié, le compte repasse « en attente ».
            if ($user->email_verified_at === null && $user->phone_verified_at === null) {
                $user->status = UserStatus::EN_ATTENTE_VERIFICATION;
            }

            $user->save();

            if (array_key_exists('preferences', $data)) {
                $user->profile()->update(['preferences' => $data['preferences']]);
            }
        });

        // Après enregistrement (l'utilisateur porte déjà le nouveau contact), on
        // émet les codes : la notification route vers le NOUVEL e-mail / numéro.
        if ($emailChanged) {
            $this->verification->issue($user, VerificationService::PURPOSE_ACCOUNT, VerificationService::CHANNEL_EMAIL);
        }
        if ($phoneChanged && $user->phone) {
            $this->verification->issue($user, VerificationService::PURPOSE_ACCOUNT, VerificationService::CHANNEL_PHONE);
        }

        return ApiResponse::success([
            'user' => UserResource::make($user->fresh()->load(['profile', 'region', 'department', 'commune'])),
            // Canaux à re-vérifier : le front affiche la saisie du code.
            'verification' => [
                'email_required' => $emailChanged,
                'phone_required' => $phoneChanged && (bool) $user->phone,
            ],
        ]);
    }

    /**
     * Changement de mot de passe. PATCH /api/v1/users/me/password
     *
     * Exige le mot de passe ACTUEL (règle `current_password`) pour éviter qu'une
     * session ouverte ne suffise à le remplacer. Les autres jetons d'accès sont
     * révoqués (déconnexion des autres appareils) ; la session courante reste.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        // Le cast `hashed` du modèle chiffre automatiquement à l'affectation.
        $user->password = $request->validated()['password'];
        $user->save();

        // Sécurité : on invalide les autres jetons (appareils), on garde le courant.
        $current = $user->currentAccessToken();
        $user->tokens()->when($current, fn ($q) => $q->where('id', '!=', $current->id))->delete();

        activity()->causedBy($user)->performedOn($user)->log('Changement de mot de passe');

        return ApiResponse::success(['message' => 'Mot de passe mis à jour.']);
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

    /**
     * Déduit la ville « texte » de la localisation structurée choisie :
     * nom de la commune si présente, sinon nom du département, sinon null.
     *
     * @param  array<string, mixed>  $data
     */
    private function deriveCity(array $data): ?string
    {
        if (! empty($data['commune_id'])) {
            return Commune::find($data['commune_id'])?->name;
        }
        if (! empty($data['department_id'])) {
            return Department::find($data['department_id'])?->name;
        }

        return null;
    }
}
