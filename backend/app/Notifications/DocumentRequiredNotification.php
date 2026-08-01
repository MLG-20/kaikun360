<?php

namespace App\Notifications;

use App\Support\Mail\BrandedMail;
use App\Support\Mail\SpaceLink;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Demande à un utilisateur de fournir un document (KYC, justificatif…) — B16.2.
 * Déclenchée par le back-office. Asynchrone, multi-canal (e-mail + SMS).
 */
class DocumentRequiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $documentType,
        public ?string $note = null,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Canaux souhaités ; arbitrage final par les réglages back-office (F7.2.l).
        return NotificationSettings::channels(
            NotificationEvent::DOCUMENT_REQUIRED,
            $notifiable,
            ['mail', 'sms'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Réclamer une pièce peut être vécu comme un contrôle tatillon. On
        // explique donc À QUOI ça sert : c'est cette vérification qui garantit
        // à l'utilisateur que les AUTRES sont vérifiés eux aussi.
        $mail = BrandedMail::make()
            ->subject('Une pièce manque à votre dossier')
            ->preheader("Document attendu : {$this->documentType}. Le dépôt prend deux minutes.")
            ->eyebrow('Dossier')
            ->tone('premium')
            ->heading('Il manque une pièce à votre dossier.')
            ->intro('Pour poursuivre l\'instruction de votre dossier, notre équipe a besoin du document suivant.')
            ->facts(['Document attendu' => $this->documentType])
            // Le lien dépend du profil : le propriétaire a un écran « Documents »
            // dédié, les autres déposent depuis leur page de profil.
            ->action('Déposer le document', SpaceLink::documents($notifiable));

        // Précision éventuellement saisie par l'agent au back-office.
        if ($this->note) {
            $mail->note($this->note);
        }

        return $mail
            ->outro('Cette vérification est la contrepartie de notre exigence : c\'est parce que chaque dossier est contrôlé que vous pouvez faire confiance aux autres utilisateurs de la plateforme.')
            ->forRecipient($notifiable)
            ->reason('Vous recevez cet e-mail car un document est attendu pour votre dossier Kaikun 360.')
            ->toMailMessage();
    }

    public function toSms(object $notifiable): string
    {
        return "Kaikun 360 : document requis ({$this->documentType}). Deposez-le dans votre espace.";
    }
}
