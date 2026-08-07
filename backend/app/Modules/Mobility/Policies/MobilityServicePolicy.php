<?php

namespace App\Modules\Mobility\Policies;

use App\Models\User;
use App\Modules\Core\Enums\ProfileVerificationStatus;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Mobility\Models\MobilityService;

/**
 * Policy de publication/gestion des DÉPARTS PROGRAMMÉS (F8.23).
 *
 * Mêmes règles que `VehiclePolicy`, et c'est voulu : un départ est une offre de
 * mobilité au même titre qu'un véhicule, déposée par le même prestataire, dans
 * le même écran. Deux règles divergentes obligeraient le prestataire à
 * comprendre pourquoi il peut publier un minibus mais pas la navette qu'il
 * opère avec.
 */
class MobilityServicePolicy
{
    /**
     * Programmer un départ : prestataire/entreprise au profil vérifié.
     */
    public function create(User $user): bool
    {
        $estPrestataire = $user->hasAnyRole([
            UserRole::PRESTATAIRE->value,
            UserRole::ENTREPRISE->value,
        ]);

        $estVerifie = $user->profile?->verification_status === ProfileVerificationStatus::VERIFIE->value;

        return $estPrestataire && $estVerifie;
    }

    /**
     * Modifier ou retirer un départ : son prestataire ou un admin.
     */
    public function update(User $user, MobilityService $service): bool
    {
        return $user->id === $service->provider_id
            || $user->hasRole(UserRole::ADMIN->value);
    }
}
