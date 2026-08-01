<?php

namespace App\Notifications;

use App\Models\Quote;
use App\Support\Mail\BrandedMail;
use App\Support\Mail\SpaceLink;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Informe le demandeur qu'un nouveau devis lui a été proposé (B16.2).
 * Asynchrone, multi-canal (e-mail + SMS).
 */
class QuoteReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Quote $quote)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Canaux souhaités ; arbitrage final par les réglages back-office (F7.2.l).
        return NotificationSettings::channels(
            NotificationEvent::QUOTE_RECEIVED,
            $notifiable,
            ['mail', 'sms', 'database'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Un devis appelle une DÉCISION : le montant et le bouton doivent sauter
        // aux yeux. On précise aussi que le prix est ferme — c'est la question
        // que tout le monde se pose en découvrant un chiffre.
        return BrandedMail::make()
            ->subject('Votre devis est prêt')
            ->preheader('Un devis vous a été adressé. À accepter ou à refuser depuis votre espace.')
            ->eyebrow('Devis')
            ->heading('Votre devis est prêt.')
            ->intro('Nous avons étudié votre demande et chiffré la prestation. Vous pouvez l\'accepter ou la refuser en un clic — rien n\'est engagé tant que vous ne l\'avez pas validée.')
            ->facts([
                'Référence' => $this->quote->reference,
                'Montant proposé' => BrandedMail::money($this->quote->amount_xof),
            ])
            ->action('Consulter le devis', SpaceLink::requests($notifiable))
            ->note('Le montant est ferme : il couvre l\'intégralité de la prestation décrite, sans frais ajouté en cours de route. Une ligne vous semble discutable ? Répondez à cet e-mail, nous en reparlons.')
            ->forRecipient($notifiable)
            ->reason('Vous recevez cet e-mail car un devis a été établi pour l\'une de vos demandes.')
            ->toMailMessage();
    }

    public function toSms(object $notifiable): string
    {
        return "Kaikun 360 : nouveau devis {$this->quote->reference} ({$this->quote->amount_xof} FCFA).";
    }

    /**
     * Charge utile du canal `database` (écran client).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'quote',
            'title' => 'Nouveau devis reçu',
            'body' => "Un devis « {$this->quote->reference} » de {$this->quote->amount_xof} FCFA vous a été proposé.",
            'action_url' => '/mon-espace/demandes',
        ];
    }
}
