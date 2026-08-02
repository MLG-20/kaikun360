<?php

namespace App\Notifications;

use App\Enums\QuoteStatus;
use App\Models\Booking;
use App\Models\Quote;
use App\Support\Mail\BrandedMail;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Prévient l'agent que SON client a tranché son devis (F8.11).
 *
 * POURQUOI ELLE EXISTE
 * --------------------
 * Un devis sur-mesure engage une relation entre deux personnes, pas une
 * transaction de catalogue. Jusqu'ici, l'acceptation d'un devis ne prévenait
 * personne : l'agent qui avait chiffré le dossier, échangé au téléphone et porté
 * la promesse commerciale apprenait la nouvelle en rouvrant un écran, ou ne
 * l'apprenait pas. Un accord client restait sans accusé de réception humain.
 *
 * ⚠️ Elle part au SEUL agent auteur du devis (`quotes.agent_id`), pas à toute
 * l'équipe : diffuser à tout le monde reviendrait à ce que personne ne se sente
 * responsable du dossier — et noierait les quatre boîtes de l'équipe.
 */
class QuoteAnsweredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param Quote        $quote   Le devis tranché.
     * @param Booking|null $booking Réservation née de l'acceptation ; null si refus.
     */
    public function __construct(
        public Quote $quote,
        public ?Booking $booking = null,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Alerte interne ; arbitrage final par les réglages back-office (F7.2.l).
        return NotificationSettings::channels(
            NotificationEvent::QUOTE_ANSWERED,
            $notifiable,
            ['mail', 'database'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $accepte = $this->quote->status === QuoteStatus::ACCEPTE;
        $client = $this->quote->request?->user?->name ?? 'Le client';

        $mail = BrandedMail::make()
            ->subject($accepte ? 'Devis accepté — '.$this->quote->reference : 'Devis refusé — '.$this->quote->reference)
            ->preheader($accepte
                ? "{$client} a accepté votre devis. La réservation est créée, le règlement attendu."
                : "{$client} a refusé votre devis {$this->quote->reference}.")
            ->eyebrow('Réponse client')
            ->tone($accepte ? 'success' : 'neutral')
            ->heading($accepte ? 'Votre devis est accepté.' : 'Votre devis a été refusé.')
            ->intro($accepte
                // Le geste utile est nommé explicitement : un accord obtenu ne
                // vaut que s'il est suivi d'un contact, pas d'une attente.
                ? "{$client} vient d'accepter le devis que vous avez établi. La réservation correspondante est créée et le montant est désormais exigible. Un appel de confirmation est le bon réflexe : c'est le moment où le client attend d'être rassuré."
                : "{$client} a refusé le devis que vous avez établi. Un refus n'est pas une fin de dossier : il vaut souvent la peine de comprendre ce qui a bloqué — le montant, le délai, ou un point resté flou.")
            ->facts(array_filter([
                'Devis' => $this->quote->reference,
                'Demande' => $this->quote->request?->reference,
                'Client' => $client,
                'Téléphone' => $this->quote->request?->user?->phone,
                'Montant' => BrandedMail::money($this->quote->amount_xof),
                'Réservation' => $this->booking?->reference,
            ]))
            ->action('Ouvrir le dossier', '/back-office/demandes/'.$this->quote->request_id)
            ->forRecipient($notifiable)
            ->reason('Vous recevez cet e-mail car vous avez établi ce devis.');

        return $mail->toMailMessage();
    }

    /**
     * Charge utile du canal `database` (cloche du back-office).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $accepte = $this->quote->status === QuoteStatus::ACCEPTE;

        return [
            'category' => 'quote',
            'title' => $accepte ? 'Devis accepté' : 'Devis refusé',
            'body' => $accepte
                ? "Le devis {$this->quote->reference} a été accepté. Réservation {$this->booking?->reference} à régler."
                : "Le devis {$this->quote->reference} a été refusé.",
            'action_url' => '/back-office/demandes/'.$this->quote->request_id,
        ];
    }
}
