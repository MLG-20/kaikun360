<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Support\Mail\BrandedMail;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
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
        // Canaux souhaités ; le filtrage effectif (événement coupé, canal coupé,
        // absence de numéro) est arbitré par les réglages back-office (F7.2.l).
        return NotificationSettings::channels(
            NotificationEvent::BOOKING_CONFIRMED,
            $notifiable,
            ['mail', 'sms', 'database'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Un e-mail de confirmation est un document que l'on GARDE : le client le
        // retrouvera dans sa boîte des semaines plus tard, à l'arrivée. Il doit
        // donc contenir tout ce dont il aura besoin ce jour-là — dates, montant,
        // référence — sans avoir à se reconnecter.
        return BrandedMail::make()
            ->subject('Votre réservation est confirmée')
            ->preheader("Réservation {$this->booking->reference} confirmée. Conservez cet e-mail.")
            ->eyebrow('Réservation')
            ->tone('success')
            ->heading('C\'est confirmé.')
            ->intro(
                'Votre paiement a bien été encaissé et votre réservation est enregistrée. Voici votre récapitulatif — conservez cet e-mail, il fait office de justificatif.'
            )
            ->facts([
                'Référence' => $this->booking->reference,
                'Arrivée' => BrandedMail::date($this->booking->start_date),
                'Départ' => BrandedMail::date($this->booking->end_date),
                'Voyageurs' => $this->booking->guests ? (string) $this->booking->guests : null,
                'Montant réglé' => BrandedMail::money($this->booking->amount_xof),
                'Caution' => BrandedMail::money($this->booking->caution_xof),
            ])
            ->action('Voir ma réservation', '/mon-espace/reservations')
            ->note('Un imprévu ? Les conditions d\'annulation et le contact de votre hôte figurent dans le détail de la réservation, depuis votre espace.')
            ->outro('Merci de votre confiance — et excellent séjour.')
            ->forRecipient($notifiable)
            ->reason('Vous recevez cet e-mail car vous avez effectué une réservation sur Kaikun 360.')
            ->toMailMessage();
    }

    public function toSms(object $notifiable): string
    {
        return "Kaikun 360 : votre reservation {$this->booking->reference} est confirmee.";
    }

    /**
     * Charge utile du canal `database` (écran client).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'booking',
            'title' => 'Réservation confirmée',
            'body' => "Votre réservation « {$this->booking->reference} » est confirmée.",
            'action_url' => '/mon-espace/reservations',
        ];
    }
}
