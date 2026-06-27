<?php

namespace App\Modules\Explore\Policies;

use App\Models\User;
use App\Modules\Core\Enums\ProfileVerificationStatus;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Explore\Models\TourismExperience;

/**
 * Policy de publication/gestion des expériences touristiques (phase B6.2).
 *
 * Règle clé du cahier des charges : seuls les PRESTATAIRES VÉRIFIÉS (KYC) peuvent
 * publier une expérience. La gestion d'une expérience donnée est réservée à son
 * prestataire (ou à un admin ; super_admin via Gate::before).
 */
class ExperiencePolicy
{
    /**
     * Publier une expérience : prestataire/entreprise au profil vérifié.
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
     * Gérer une expérience existante : son prestataire ou un admin.
     */
    public function update(User $user, TourismExperience $experience): bool
    {
        return $user->id === $experience->provider_id
            || $user->hasRole(UserRole::ADMIN->value);
    }
}
