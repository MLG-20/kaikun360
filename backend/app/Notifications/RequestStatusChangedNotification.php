<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifie le demandeur de l'avancement de sa demande.
 *
 * Mise en FILE (ShouldQueue) — le « Job de notification » du cahier des charges.
 * Canal mail en dev ; push/WhatsApp viendront en phase B16.
 */
class RequestStatusChangedNotification extends Notification implements ShouldQueue
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
            ->subject('Votre demande a avancé — Kaikun 360')
            ->line("Votre demande « {$this->request->reference} » est passée au statut : {$this->request->status->label()}.");
    }
}
