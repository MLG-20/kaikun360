<?php

namespace App\Modules\Core\Services;

use App\Models\User;
use App\Modules\Core\Models\VerificationCode;
use App\Modules\Core\Notifications\VerificationCodeNotification;
use Illuminate\Support\Facades\Hash;

/**
 * Service de gestion des codes de vérification / réinitialisation (phase B1.4).
 *
 * Centralise toute la logique : génération d'un code à 6 chiffres, stockage
 * sécurisé (hash uniquement), envoi via Notification, et vérification.
 * Aucune autre couche ne manipule directement les codes.
 */
class VerificationService
{
    /** Finalités possibles d'un code. */
    public const PURPOSE_ACCOUNT = 'account_verification';
    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    /** Canaux possibles. */
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_PHONE = 'phone';

    /** Durée de validité d'un code, en minutes. */
    public const TTL_MINUTES = 15;

    /**
     * Génère un nouveau code, l'enregistre (haché) et l'envoie à l'utilisateur.
     *
     * Tout code précédent non consommé pour le même usage/canal est invalidé,
     * afin qu'un seul code soit actif à la fois.
     */
    public function issue(User $user, string $purpose, string $channel): void
    {
        // Invalide les anciens codes encore en attente pour ce couple usage/canal.
        VerificationCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->where('channel', $channel)
            ->whereNull('consumed_at')
            ->delete();

        // Code numérique à 6 chiffres (random_int = générateur cryptographiquement sûr).
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        VerificationCode::create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'channel' => $channel,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        // Envoi (en dev : loggé via le mailer "log").
        $user->notify(new VerificationCodeNotification($code, $purpose, $channel));
    }

    /**
     * Vérifie un code fourni par l'utilisateur.
     *
     * Retourne true et consomme le code s'il est valide ; false sinon.
     */
    public function verify(User $user, string $purpose, string $channel, string $code): bool
    {
        // Dernier code valide (non consommé, non expiré) pour cet usage/canal.
        $record = VerificationCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->where('channel', $channel)
            ->valid()
            ->latest()
            ->first();

        if (! $record || ! Hash::check($code, $record->code_hash)) {
            return false;
        }

        // Usage unique : on marque le code comme consommé.
        $record->update(['consumed_at' => now()]);

        return true;
    }
}
