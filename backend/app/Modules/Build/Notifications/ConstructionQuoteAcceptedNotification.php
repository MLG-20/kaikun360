<?php

namespace App\Modules\Build\Notifications;

use App\Models\Booking;
use App\Modules\Build\Models\ConstructionQuote;
use App\Support\Mail\BrandedMail;
use App\Support\Mail\SpaceLink;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Prévient le client que son devis de chantier accepté attend son règlement
 * (F8.14).
 *
 * POURQUOI ELLE EXISTE. Accepter un devis de chantier ne produisait aucune
 * réservation : le client validait un projet à plusieurs millions et rien ne
 * devenait exigible — ni montant, ni écran, ni relance. L'acceptation restait un
 * statut en base. Maintenant qu'elle crée une réservation payable, il faut le
 * lui dire, avec le montant et le chemin pour le régler.
 *
 * ⚠️ Réutilise l'événement de réglage `TEAM_BUILDING_QUOTE_ACCEPTED` — mal nommé
 * pour ce cas, mais il désigne exactement la même règle métier (« un devis
 * accepté devient exigible, prévenir le client ») : ajouter un interrupteur par
 * univers obligerait l'équipe à décocher trois cases pour une seule décision.
 * Renommer l'enum casserait les réglages déjà enregistrés en base.
 *
 * ⚠️ Le lien passe par {@see SpaceLink} : un chantier peut être suivi par un
 * client comme par un compte diaspora, qui n'ont pas le même espace.
 */
class ConstructionQuoteAcceptedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ConstructionQuote $quote,
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
            ->preheader('Votre chantier est engagé. Il reste à régler pour le lancer.')
            ->eyebrow('Construction')
            ->tone('success')
            ->heading('Votre devis de chantier est accepté.')
            // Un chantier ne démarre pas sur un accord : il démarre sur un
            // versement. Le dire évite au client d'attendre un coup de pelle qui
            // ne viendra pas.
            ->intro("Votre accord est enregistré et le dossier est créé. Le règlement reste attendu : c'est lui qui déclenche le démarrage des travaux et la mobilisation des équipes. Un acompte est possible — le solde peut être réglé plus tard.")
            ->facts(array_filter([
                'Devis' => $this->quote->reference,
                'Dossier' => $this->booking->reference,
                'Montant' => BrandedMail::money($this->booking->amount_xof),
            ]))
            ->action('Régler mon chantier', $lien)
            ->forRecipient($notifiable)
            ->reason('Vous recevez cet e-mail car vous avez accepté ce devis.')
            ->toMailMessage();
    }

    /**
     * Charge utile du canal `database` (cloche de l'espace du client).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'quote',
            'title' => 'Devis de chantier accepté',
            'body' => "Votre devis {$this->quote->reference} est accepté. Dossier {$this->booking->reference} à régler.",
            'action_url' => SpaceLink::to($notifiable, 'reservations/'.$this->booking->id.'/paiement'),
        ];
    }
}
