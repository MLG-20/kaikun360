<?php

namespace App\Modules\Build\Notifications;

use App\Modules\Build\Models\ConstructionQuote;
use App\Support\Mail\BrandedMail;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Prévient le client qu'un devis de chantier lui a été envoyé (F3.9).
 *
 * Deux canaux :
 *   - `mail` — la trace écrite, avec le montant : un devis est un engagement
 *     financier, le client doit en garder une preuve hors de l'application ;
 *   - `database` — alimente la **cloche** et l'écran « Notifications » de
 *     l'espace client. C'est lui qui rend la fonctionnalité utilisable : sans
 *     notification in-app, l'écran d'acceptation existe mais personne ne sait
 *     qu'il faut y aller.
 *
 * L'événement de réglage réutilisé est `QUOTE_RECEIVED` (« Au client, quand un
 * devis lui est adressé »), déjà porté par le devis transversal : couper « Devis
 * reçu » au back-office coupe cohéremment TOUS les devis, plutôt que d'obliger
 * l'équipe à décocher une case par univers.
 */
class ConstructionQuoteSentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ConstructionQuote $quote) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Arbitrage par les réglages back-office (F7.2.l).
        return NotificationSettings::channels(
            NotificationEvent::QUOTE_RECEIVED,
            $notifiable,
            ['mail', 'database'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Un devis de chantier engage des sommes importantes et se décide
        // rarement seul : on insiste donc sur le détail par lot et sur la
        // possibilité d'en discuter, plutôt que d'asséner un total.
        return BrandedMail::make()
            ->subject('Votre devis de chantier est prêt')
            ->preheader("Devis {$this->quote->reference} : détail par lot et montant total.")
            ->eyebrow('Construction')
            ->heading('Votre devis de chantier est prêt.')
            ->intro(
                'Nous avons chiffré votre projet lot par lot — gros œuvre, second œuvre, finitions — à partir des éléments que vous nous avez transmis. Rien n\'est engagé tant que vous n\'avez pas accepté.'
            )
            ->facts([
                'Référence' => $this->quote->reference,
                'Sous-total' => BrandedMail::money($this->quote->subtotal_xof),
                'Montant total' => BrandedMail::money($this->quote->total_xof),
                'Valable jusqu\'au' => BrandedMail::date($this->quote->valid_until),
            ])
            ->action('Consulter le détail du devis', '/mon-espace/demandes')
            ->note('Le détail ligne par ligne est consultable dans votre espace. Un poste vous semble surdimensionné ou incomplet ? Répondez à cet e-mail : nous réajustons le chiffrage avec vous, sans engagement.')
            ->forRecipient($notifiable)
            ->reason('Vous recevez cet e-mail car un devis a été établi pour votre projet de construction.')
            ->toMailMessage();
    }

    /**
     * Charge utile du canal `database` (cloche + écran « Notifications »).
     *
     * `action_url` mène à la rubrique où le devis se répond — la même que celle
     * du rail de l'espace client, pour que le clic tombe juste du premier coup.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'construction',
            'title' => 'Votre devis de chantier est prêt',
            'body' => "Le devis « {$this->quote->reference} » vous a été envoyé (total : {$this->quote->total_xof} XOF).",
            'action_url' => '/mon-espace/diaspora',
        ];
    }
}
