<?php

namespace App\Modules\Admin\Validation;

use App\Models\User;

/**
 * Normalise le **déposant** d'une ressource pour la file de validation (F7.2.a).
 *
 * L'agent qui modère un bien / véhicule / expérience / prestataire a besoin de
 * savoir QUI l'a soumis (identité + contact), pas seulement un identifiant. Ce
 * helper produit la même forme pour tous les types, à partir du compte
 * utilisateur rattaché (propriétaire, prestataire…).
 */
final class OwnerEntry
{
    /**
     * @return array{id:int,name:string,email:string|null,phone:string|null}|null
     */
    public static function from(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
        ];
    }
}
