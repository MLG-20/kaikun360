<?php

namespace App\Modules\Mobility\Notifications;

use App\Modules\Mobility\Models\Vehicle;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifie le prestataire que son véhicule est validé et visible dans la recherche.
 *
 * Canal mail (loggé en dev). Le push/WhatsApp viendra en phase B16.
 */
class VehicleValidatedNotification extends Notification
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
            ->subject('Votre véhicule est en ligne — Kaikun 360')
            ->line("Votre véhicule « {$this->vehicle->reference} » a été validé.")
            ->line('Il apparaît désormais dans la recherche.');
    }
}
