<?php

namespace App\Modules\TeamBuilding\Notifications;

use App\Modules\TeamBuilding\Models\TeamBuildingQuote;
use App\Support\Mail\BrandedMail;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notifie l'entreprise qu'un devis de team building lui a été envoyé.
 *
 * Deux canaux :
 *   - `mail` (loggé en dev) — trace écrite pour l'entreprise ;
 *   - `database` — alimente la **cloche** + l'écran « Notifications » de l'espace
 *     entreprise (F6, cahier §5 « Notifications = Tous »). Sans lui, l'entreprise
 *     ne verrait jamais in-app qu'un devis l'attend et devrait ouvrir « Mes
 *     demandes » à l'aveugle.
 *
 * Le push/WhatsApp viendra en phase B16.
 */
class TeamBuildingQuoteSentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TeamBuildingQuote $quote) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Arbitrage par les réglages back-office (F7.2.l).
        return NotificationSettings::channels(
            NotificationEvent::TEAM_BUILDING_QUOTE,
            $notifiable,
            ['mail', 'database'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Destinataire : une ENTREPRISE. Le décideur a besoin d'un chiffre net,
        // d'un document opposable et d'un interlocuteur — dans cet ordre.
        return BrandedMail::make()
            ->subject('Votre devis team building est prêt')
            ->preheader("Devis {$this->quote->reference} : hébergement, restauration, transport et animation réunis.")
            ->eyebrow('Team building')
            ->heading('Votre devis est prêt.')
            ->intro(
                'Nous avons assemblé votre événement : hébergement, restauration, transport et animation réunis en une seule proposition, chiffrée poste par poste.'
            )
            ->facts([
                'Référence' => $this->quote->reference,
                'Sous-total prestations' => BrandedMail::money($this->quote->subtotal_xof),
                'Montant total' => BrandedMail::money($this->quote->total_xof),
            ])
            ->action('Consulter le devis', '/espace-entreprise/demandes')
            ->note('Besoin d\'ajuster le nombre de participants, les dates ou un poste précis ? Votre interlocuteur Kaikun reprend le chiffrage avec vous — le devis reste modifiable jusqu\'à votre validation.')
            ->outro('Une facture conforme, avec le détail par poste de dépense, vous sera adressée après acceptation.')
            ->forRecipient($notifiable)
            ->reason('Vous recevez cet e-mail car votre entreprise a sollicité un devis sur Kaikun 360.')
            ->toMailMessage();
    }

    /**
     * Charge utile du canal `database` (cloche + écran « Notifications »).
     *
     * `action_url` mène au détail de la demande dans l'espace entreprise, où le
     * devis est consultable et acceptable (F6).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'team_building',
            'title' => 'Votre devis team building est prêt',
            'body' => "Le devis « {$this->quote->reference} » vous a été envoyé (total : {$this->quote->total_xof} XOF).",
            'action_url' => '/espace-entreprise/demandes/'.$this->quote->request_id,
        ];
    }
}
