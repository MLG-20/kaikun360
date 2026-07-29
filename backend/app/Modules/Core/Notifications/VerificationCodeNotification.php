<?php

namespace App\Modules\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification d'envoi d'un code (vérification de compte ou reset mot de passe).
 *
 * Multi-canal (B16.1) : SMS quand le code est destiné au TÉLÉPHONE, e-mail
 * sinon. L'envoi est asynchrone (ShouldQueue) pour ne jamais bloquer la requête.
 * En dev, MAIL_MAILER=log et le fournisseur SMS `log` écrivent dans les logs
 * (aucun envoi réel). Le SMS réel (Twilio) s'active via `SMS_PROVIDER=twilio`.
 */
class VerificationCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $code,
        public string $purpose,
        public string $channel,
    ) {}

    /**
     * Canaux de livraison. Mail uniquement pour l'instant.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // ⚠️ Notification de SÉCURITÉ : volontairement EXCLUE du pilotage des
        // notifications du back-office (F7.2.l). Couper ce canal condamnerait
        // l'accès (2FA admin) et l'inscription — aucun réglage ne doit pouvoir
        // le faire. Voir App\Support\Notifications\NotificationEvent.
        // Code destiné au téléphone → SMS ; sinon e-mail.
        return $this->channel === 'phone' ? ['sms'] : ['mail'];
    }

    /**
     * Contenu du SMS (canal `sms`, B16.1).
     */
    public function toSms(object $notifiable): string
    {
        return "Kaikun 360 : votre code est {$this->code}. Valable 15 min, ne le partagez pas.";
    }

    /**
     * Contenu de l'e-mail.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $intro = match ($this->purpose) {
            'password_reset' => 'Voici votre code de réinitialisation de mot de passe Kaikun 360 :',
            'two_factor' => 'Voici votre code de connexion au back-office Kaikun 360 (double authentification) :',
            default => 'Voici votre code de vérification Kaikun 360 :',
        };

        return (new MailMessage)
            ->subject('Votre code Kaikun 360')
            ->greeting('Bonjour,')
            ->line($intro)
            ->line("**{$this->code}**")
            ->line('Ce code est valable 15 minutes. Ne le partagez avec personne.');
    }
}
