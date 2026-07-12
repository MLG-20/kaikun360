<?php

namespace App\Notifications;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Informe le demandeur qu'un nouveau devis lui a été proposé (B16.2).
 * Asynchrone, multi-canal (e-mail + SMS).
 */
class QuoteReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Quote $quote)
    {
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
        return (new MailMessage)
            ->subject('Nouveau devis — Kaikun 360')
            ->greeting('Bonjour,')
            ->line("Un devis « {$this->quote->reference} » vous a été proposé.")
            ->line("Montant : {$this->quote->amount_xof} FCFA.")
            ->line('Connectez-vous pour l\'accepter ou le refuser.');
    }

    public function toSms(object $notifiable): string
    {
        return "Kaikun 360 : nouveau devis {$this->quote->reference} ({$this->quote->amount_xof} FCFA).";
    }
}
