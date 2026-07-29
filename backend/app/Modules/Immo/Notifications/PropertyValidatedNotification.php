<?php

namespace App\Modules\Immo\Notifications;

use App\Modules\Immo\Models\Property;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Informe le propriétaire que son bien a été validé et publié.
 */
class PropertyValidatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Property $property) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Arbitrage par les réglages back-office (F7.2.l) : événement « Offre
        // validée », commun aux biens et aux véhicules.
        return NotificationSettings::channels(
            NotificationEvent::RESOURCE_VALIDATED,
            $notifiable,
            ['mail'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre bien est en ligne — Kaikun 360')
            ->greeting('Bonne nouvelle !')
            ->line("Votre bien « {$this->property->title} » a été validé et publié sur Kaikun 360.");
    }
}
