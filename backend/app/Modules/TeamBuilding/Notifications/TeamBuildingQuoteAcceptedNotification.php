<?php

namespace App\Modules\TeamBuilding\Notifications;

use App\Models\Booking;
use App\Modules\TeamBuilding\Models\TeamBuildingQuote;
use App\Support\Mail\BrandedMail;
use App\Support\Mail\SpaceLink;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Prévient l'ENTREPRISE que son devis team building est accepté et que la
 * réservation correspondante l'attend au règlement (F8.14).
 *
 * POURQUOI ELLE EXISTE
 * --------------------
 * Accepter un devis ne produisait, jusqu'à cette tranche, aucune réservation et
 * donc rien à payer : le parcours s'arrêtait sur un statut changé, sans que rien
 * ne dise à l'entreprise ce qu'il fallait faire ensuite. Maintenant qu'un montant
 * devient exigible, il faut le lui dire — un accord suivi d'un silence est la
 * meilleure façon de perdre un séminaire déjà vendu.
 *
 * ⚠️ Le lien pointe vers `/espace-entreprise/...`, résolu par {@see SpaceLink}
 * d'après le PROFIL du destinataire. C'est la leçon de F8.8 : le site a quatre
 * espaces connectés, et un lien codé en dur sur `/mon-espace` enverrait
 * l'entreprise sur une page qu'elle n'a pas le droit d'ouvrir (les espaces sont
 * cloisonnés par rôle).
 *
 * ⚠️ Une seule notification part de cette acceptation, et elle va au client.
 * Côté interne, l'événement `QuoteAccepted` trace déjà le dossier (journal
 * d'activité) et la demande change de statut dans le back-office : ajouter une
 * alerte à l'équipe ne dirait rien de neuf et arroserait quatre boîtes.
 */
class TeamBuildingQuoteAcceptedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public TeamBuildingQuote $quote,
        public Booking $booking,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return NotificationSettings::channels(
            NotificationEvent::TEAM_BUILDING_QUOTE_ACCEPTED,
            $notifiable,
            ['mail', 'database'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lien = SpaceLink::to($notifiable, 'reservations/'.$this->booking->id.'/paiement');

        return BrandedMail::make()
            ->subject('Devis accepté — '.$this->quote->reference)
            ->preheader('Votre séminaire est réservé. Il reste à le régler.')
            ->eyebrow('Team building')
            ->tone('success')
            ->heading('Votre devis est accepté.')
            // On dit d'emblée ce qui est acquis ET ce qui manque : une entreprise
            // qui croit son événement bloqué alors qu'il n'attend qu'un règlement
            // perd des jours — et le prestataire avec elle.
            ->intro("Votre accord est enregistré et votre réservation est créée. Elle reste en attente de règlement : c'est le paiement qui la confirme définitivement et déclenche la réservation des prestataires.")
            ->facts(array_filter([
                'Devis' => $this->quote->reference,
                'Réservation' => $this->booking->reference,
                'Participants' => $this->booking->guests,
                'Montant' => BrandedMail::money($this->booking->amount_xof),
            ]))
            ->action('Régler ma réservation', $lien)
            ->forRecipient($notifiable)
            ->reason('Vous recevez cet e-mail car vous avez accepté ce devis.')
            ->toMailMessage();
    }

    /**
     * Charge utile du canal `database` (cloche de l'espace entreprise).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'quote',
            'title' => 'Devis accepté',
            'body' => "Votre devis {$this->quote->reference} est accepté. Réservation {$this->booking->reference} à régler.",
            'action_url' => SpaceLink::to($notifiable, 'reservations/'.$this->booking->id.'/paiement'),
        ];
    }
}
