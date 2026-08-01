<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use App\Support\Mail\BrandedMail;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notifie un agent qu'une nouvelle demande client attend un traitement.
 *
 * Canal mail (loggé en dev). Le push/WhatsApp viendra en phase B16.
 */
class NewRequestToHandleNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ServiceRequest $request) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Alerte interne ; arbitrage par les réglages back-office (F7.2.l).
        return NotificationSettings::channels(
            NotificationEvent::NEW_REQUEST_TO_HANDLE,
            $notifiable,
            ['mail'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Alerte INTERNE : le destinataire est un agent, pas un client. Le ton
        // est donc opérationnel et l'information dense — mais l'habillage reste
        // le même : l'équipe doit voir les mêmes standards que ses clients.
        return BrandedMail::make()
            ->subject('Nouvelle demande à traiter')
            ->preheader("Demande {$this->request->reference} en attente de prise en charge.")
            ->eyebrow('File de traitement')
            ->heading('Une demande attend une prise en charge.')
            ->intro('Une nouvelle demande client vient d\'arriver dans la file. Le délai de première réponse court à partir de maintenant.')
            ->facts([
                'Référence' => $this->request->reference,
                'Service' => $this->request->service_type?->label(),
                'Ville' => $this->request->city,
                'Budget indiqué' => BrandedMail::money($this->request->budget_xof),
                'Priorité' => $this->request->priority?->label(),
                'Reçue le' => BrandedMail::date($this->request->created_at),
            ])
            ->action('Ouvrir la file de traitement', '/back-office')
            ->forRecipient($notifiable)
            ->reason('Vous recevez cet e-mail en tant que membre de l\'équipe Kaikun 360.')
            ->toMailMessage();
    }
}
