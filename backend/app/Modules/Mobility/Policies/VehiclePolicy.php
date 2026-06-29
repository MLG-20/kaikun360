<?php

namespace App\Modules\Mobility\Policies;

use App\Models\User;
use App\Modules\Core\Enums\ProfileVerificationStatus;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Mobility\Models\Vehicle;

/**
 * Policy de publication/gestion des véhicules (phase B7.3).
 *
 * Seuls les PRESTATAIRES VÉRIFIÉS peuvent publier un véhicule ; un prestataire ne
 * modifie que ses propres véhicules (ou un admin ; super_admin via Gate::before).
 */
class VehiclePolicy
{
    /**
     * Publier un véhicule : prestataire/entreprise au profil vérifié.
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
     * Modifier un véhicule : son prestataire ou un admin.
     */
    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->id === $vehicle->provider_id
            || $user->hasRole(UserRole::ADMIN->value);
    }
}
