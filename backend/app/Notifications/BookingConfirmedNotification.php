<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Confirme au client que sa réservation est validée (paiement encaissé, B16.2).
 * Asynchrone, multi-canal (e-mail + SMS).
 */
class BookingConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking)
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
            ->subject('Réservation confirmée — Kaikun 360')
            ->greeting('Bonjour,')
            ->line("Votre réservation « {$this->booking->reference} » est confirmée.")
            ->line("Montant : {$this->booking->amount_xof} FCFA.")
            ->line('Merci de votre confiance.');
    }

    public function toSms(object $notifiable): string
    {
        return "Kaikun 360 : votre reservation {$this->booking->reference} est confirmee.";
    }
}
