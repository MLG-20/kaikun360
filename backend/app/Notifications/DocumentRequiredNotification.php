<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Demande à un utilisateur de fournir un document (KYC, justificatif…) — B16.2.
 * Déclenchée par le back-office. Asynchrone, multi-canal (e-mail + SMS).
 */
class DocumentRequiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $documentType,
        public ?string $note = null,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable->phone ? ['mail', 'sms'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Document requis — Kaikun 360')
            ->greeting('Bonjour,')
            ->line("Merci de fournir le document suivant : {$this->documentType}.");

        if ($this->note) {
            $mail->line($this->note);
        }

        return $mail->line('Connectez-vous à votre espace pour le déposer.');
    }

    public function toSms(object $notifiable): string
    {
        return "Kaikun 360 : document requis ({$this->documentType}). Deposez-le dans votre espace.";
    }
}
