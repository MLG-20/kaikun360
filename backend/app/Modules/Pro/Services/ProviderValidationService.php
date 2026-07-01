<?php

namespace App\Modules\Pro\Services;

use App\Models\User;
use App\Modules\Core\Enums\ProfileVerificationStatus;
use App\Modules\Pro\Enums\ProviderStatus;
use App\Modules\Pro\Models\Provider;

/**
 * Validation / sanction d'un prestataire marketplace (phase B10.2).
 *
 * Règle centrale : le statut du prestataire pilote le `verification_status` de
 * son profil (Core). Ainsi, valider un prestataire débloque la publication de
 * services publics (Explore B6, Mobility B7) ; le suspendre/refuser la bloque.
 */
class ProviderValidationService
{
    /**
     * Seuil d'avertissements au-delà duquel le prestataire est suspendu d'office.
     */
    public const SUSPENSION_THRESHOLD = 3;

    /**
     * Valide un prestataire et débloque la publication (profil vérifié).
     */
    public function validate(Provider $provider, User $agent): Provider
    {
        $provider->update([
            'status' => ProviderStatus::VALIDE->value,
            'validated_at' => now(),
            'validated_by' => $agent->id,
        ]);

        $this->syncProfileVerification($provider, ProfileVerificationStatus::VERIFIE);

        return $provider->refresh();
    }

    /**
     * Refuse un prestataire (publication non débloquée).
     */
    public function reject(Provider $provider): Provider
    {
        $provider->update(['status' => ProviderStatus::REFUSE->value]);
        $this->syncProfileVerification($provider, ProfileVerificationStatus::REJETE);

        return $provider->refresh();
    }

    /**
     * Suspend un prestataire : bloque la publication (profil non vérifié).
     */
    public function suspend(Provider $provider, ?string $reason = null): Provider
    {
        $provider->update([
            'status' => ProviderStatus::SUSPENDU->value,
            'sanction_note' => $reason ?? $provider->sanction_note,
        ]);
        $this->syncProfileVerification($provider, ProfileVerificationStatus::NON_VERIFIE);

        return $provider->refresh();
    }

    /**
     * Émet un avertissement (charte qualité). Suspend au-delà du seuil.
     */
    public function warn(Provider $provider, ?string $reason = null): Provider
    {
        $provider->increment('warnings_count');
        if ($reason !== null) {
            $provider->update(['sanction_note' => $reason]);
        }
        $provider->refresh();

        if ($provider->warnings_count >= self::SUSPENSION_THRESHOLD) {
            return $this->suspend($provider, $reason);
        }

        return $provider;
    }

    /**
     * Aligne le `verification_status` du profil de l'utilisateur prestataire.
     */
    private function syncProfileVerification(Provider $provider, ProfileVerificationStatus $status): void
    {
        $provider->user?->profile?->update(['verification_status' => $status->value]);
    }
}
