<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifie un agent qu'une nouvelle demande client attend un traitement.
 *
 * Canal mail (loggé en dev). Le push/WhatsApp viendra en phase B16.
 */
class NewRequestToHandleNotification extends Notification
{
    use Queueable;

    public function __construct(public ServiceRequest $request) {}

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
            ->subject('Nouvelle demande client — Kaikun 360')
            ->line("Demande « {$this->request->reference} » ({$this->request->service_type->label()}).")
            ->line('À prendre en charge dans la file de traitement.');
    }
}
