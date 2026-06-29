<?php

namespace App\Modules\Mobility\Notifications;

use App\Modules\Mobility\Models\Vehicle;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifie un agent qu'un nouveau véhicule attend sa validation.
 *
 * Canal mail (loggé en dev). Le push/WhatsApp viendra en phase B16.
 */
class NewVehicleToValidateNotification extends Notification
{
    use Queueable;

    public function __construct(public Vehicle $vehicle) {}

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
            ->subject('Nouveau véhicule à valider — Kaikun 360')
            ->line("Un nouveau véhicule a été déposé : « {$this->vehicle->reference} » ({$this->vehicle->type->label()}).")
            ->line('Merci de vérifier sa conformité dans la file de validation.');
    }
}
