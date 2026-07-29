<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notifie un agent qu'une nouvelle demande client attend un traitement.
 *
 * Canal mail (loggé en dev). Le push/WhatsApp viendra en phase B16.
 */
class NewRequestToHandleNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ServiceRequest $request) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Alerte interne ; arbitrage par les réglages back-office (F7.2.l).
        return NotificationSettings::channels(
            NotificationEvent::NEW_REQUEST_TO_HANDLE,
            $notifiable,
            ['mail'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nouvelle demande client — Kaikun 360')
            ->line("Demande « {$this->request->reference} » ({$this->request->service_type->label()}).")
            ->line('À prendre en charge dans la file de traitement.');
    }
}
