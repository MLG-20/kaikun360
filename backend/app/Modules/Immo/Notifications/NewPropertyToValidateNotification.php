<?php

namespace App\Modules\Immo\Notifications;

use App\Modules\Immo\Models\Property;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifie un agent qu'un nouveau bien attend sa validation.
 *
 * Canal mail (loggé en dev). Le push/WhatsApp viendra en phase B16.
 */
class NewPropertyToValidateNotification extends Notification
{
    use Queueable;

    public function __construct(public Property $property) {}

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
            ->subject('Nouveau bien à valider — Kaikun 360')
            ->line("Un nouveau bien a été déposé : « {$this->property->title} ».")
            ->line('Merci de le vérifier dans la file de validation.');
    }
}
