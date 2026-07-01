<?php

namespace App\Modules\TeamBuilding\Notifications;

use App\Modules\TeamBuilding\Models\TeamBuildingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifie l'équipe back-office d'une nouvelle demande de team building.
 *
 * Canal mail (loggé en dev). Le push/WhatsApp viendra en phase B16.
 */
class NewTeamBuildingRequestNotification extends Notification
{
    use Queueable;

    public function __construct(public TeamBuildingRequest $request) {}

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
            ->subject('Nouvelle demande de team building — Kaikun 360')
            ->line("Demande « {$this->request->reference} » ({$this->request->participants} participants à {$this->request->city}).")
            ->line('À traiter dans la file d\'attente dédiée.');
    }
}
