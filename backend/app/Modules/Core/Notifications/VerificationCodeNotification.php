<?php

namespace App\Modules\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification d'envoi d'un code (vérification de compte ou reset mot de passe).
 *
 * Pour l'instant le seul canal est "mail" : en développement, MAIL_MAILER=log,
 * donc le code apparaît dans storage/logs/laravel.log (aucun coût, aucun envoi réel).
 *
 * 👉 À FAIRE plus tard (phase B16) : ajouter un canal SMS réel (Twilio, etc.)
 *    pour les codes envoyés par téléphone.
 */
class VerificationCodeNotification extends Notification
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
        return ['mail'];
    }

    /**
     * Contenu de l'e-mail.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $intro = $this->purpose === 'password_reset'
            ? 'Voici votre code de réinitialisation de mot de passe Kaikun 360 :'
            : 'Voici votre code de vérification Kaikun 360 :';

        return (new MailMessage)
            ->subject('Votre code Kaikun 360')
            ->greeting('Bonjour,')
            ->line($intro)
            ->line("**{$this->code}**")
            ->line('Ce code est valable 15 minutes. Ne le partagez avec personne.');
    }
}
