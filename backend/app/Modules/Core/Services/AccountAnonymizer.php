<?php

namespace App\Modules\Core\Services;

use App\Models\User;
use App\Modules\Core\Enums\UserStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Anonymisation d'un compte sur demande (RGPD, B15.4).
 *
 * On n'efface PAS le compte physiquement : les réservations et paiements
 * doivent être conservés pour des raisons comptables et légales (durée de
 * conservation par type de donnée). On SCRUBE en revanche toutes les données à
 * caractère personnel (identité, contacts, pièces d'identité) et on désactive le
 * compte, rendant l'utilisateur non identifiable.
 */
class AccountAnonymizer
{
    /**
     * Anonymise l'utilisateur et coupe tous ses accès.
     */
    public function anonymize(User $user): void
    {
        DB::transaction(function () use ($user) {
            // 1) Pièces d'identité (KYC) : fichiers + enregistrements supprimés.
            foreach ($user->documents as $document) {
                if ($document->path) {
                    Storage::disk($document->disk)->delete($document->path);
                }
                $document->delete();
            }

            // 2) Profil : préférences et données personnelles neutralisées.
            $user->profile?->update(['preferences' => null]);

            // 3) Identité : neutralisée, e-mail rendu unique et non nominatif.
            $user->forceFill([
                'name' => 'Utilisateur supprimé',
                'email' => "deleted-{$user->id}@anonymized.local",
                'phone' => null,
                'city' => null,
                'password' => bcrypt(Str::random(40)),
                'email_verified_at' => null,
                'phone_verified_at' => null,
                'status' => UserStatus::DESACTIVE->value,
            ])->save();

            // 4) Accès : tous les jetons Sanctum révoqués.
            $user->tokens()->delete();

            activity()->causedBy($user)->performedOn($user)->log('Anonymisation de compte (RGPD)');
        });
    }
}
