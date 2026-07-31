<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Enums\ProfileType;
use App\Modules\Core\Http\Requests\UpdateAvatarRequest;
use App\Modules\Core\Http\Resources\UserResource;
use App\Modules\Core\Models\Profile;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Photo de profil / logo d'entreprise du compte connecté (F8.0).
 *
 * Le besoin est d'IDENTIFICATION : un client, un propriétaire et un prestataire
 * se reconnaissent à leur visage, une entreprise à son logo. La colonne de
 * stockage est unique (`profiles.avatar_path`) et c'est le type de profil qui
 * décide du sens de l'image — voir `Profile::avatarKind()`.
 *
 * ⚠️ Disque PUBLIC, à la différence des pièces justificatives (`DocumentController`,
 * disque privé + URL signée). Une photo de profil s'affiche en permanence : une
 * URL signée expirerait au milieu d'une session, image cassée à l'écran. En
 * contrepartie, `UpdateAvatarRequest` n'accepte que de vraies images matricielles
 * (ni PDF, ni SVG — ce dernier peut porter du script).
 *
 * Les deux routes renvoient l'utilisateur complet plutôt que la seule URL : le
 * front garde ainsi UNE source de vérité pour le compte connecté (en-tête,
 * page profil), au lieu de recoller un champ à la main dans son état local.
 */
class AvatarController extends Controller
{
    /**
     * Dépose (ou remplace) la photo / le logo. POST /api/v1/users/me/avatar
     *
     * Envoi multipart, champ `avatar`.
     */
    public function store(UpdateAvatarRequest $request): JsonResponse
    {
        $user = $request->user();
        $profile = $this->profileFor($request);

        // L'ANCIEN fichier part AVANT d'enregistrer le nouveau chemin. Sans ça,
        // chaque changement de photo laisserait sur le disque un fichier que
        // plus aucune ligne ne référence — invisible, et jamais nettoyé.
        $profile->deleteAvatarFile();

        // store() génère un nom aléatoire : pas de collision entre deux
        // « photo.jpg », et le nom d'origine (potentiellement hostile) ne se
        // retrouve jamais dans une URL publique.
        $path = $request->file('avatar')->store(
            "avatars/{$user->id}",
            Profile::AVATAR_DISK,
        );

        $profile->update(['avatar_path' => $path]);

        activity()->causedBy($user)->performedOn($profile)
            ->log($profile->avatarKind() === 'logo' ? 'Mise à jour du logo' : 'Mise à jour de la photo de profil');

        return ApiResponse::success([
            'user' => UserResource::make($user->load('profile'))->withPermissions(),
        ]);
    }

    /**
     * Retire la photo / le logo. DELETE /api/v1/users/me/avatar
     *
     * Idempotent : supprimer une image absente répond 200, pas 404 — le compte
     * est bien dans l'état demandé (sans image), et l'interface n'a pas à
     * traiter un cas d'erreur pour un double clic.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $this->profileFor($request);

        $profile->deleteAvatarFile();
        $profile->update(['avatar_path' => null]);

        return ApiResponse::success([
            'user' => UserResource::make($user->load('profile'))->withPermissions(),
        ]);
    }

    /**
     * Le profil du compte connecté, créé au vol s'il manque.
     *
     * Le profil est normalement posé à l'inscription. Le `firstOrCreate` couvre
     * les comptes plus anciens (et ceux fabriqués par un seeder sans profil) :
     * refuser une photo à cause d'une ligne manquante serait incompréhensible
     * pour l'utilisateur. Le type par défaut reste celui de l'enum.
     */
    private function profileFor(Request $request): Profile
    {
        return Profile::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['type' => ProfileType::CLIENT->value],
        );
    }
}
