<?php

namespace App\Modules\TeamBuilding\Notifications;

use App\Modules\TeamBuilding\Models\TeamBuildingQuote;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifie l'entreprise qu'un devis de team building lui a été envoyé.
 *
 * Canal mail (loggé en dev). Le push/WhatsApp viendra en phase B16.
 */
class TeamBuildingQuoteSentNotification extends Notification
{
    use Queueable;

    public function __construct(public TeamBuildingQuote $quote) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre devis team building — Kaikun 360')
            ->line("Un devis « {$this->quote->reference} » vous a été envoyé.")
            ->line("Montant total : {$this->quote->total_xof} XOF.")
            ->line('Connectez-vous pour l\'accepter.');
    }
}
