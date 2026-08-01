<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use App\Support\Mail\BrandedMail;
use App\Support\Mail\SpaceLink;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifie le demandeur de l'avancement de sa demande.
 *
 * Mise en FILE (ShouldQueue) — le « Job de notification » du cahier des charges.
 * Canal mail en dev ; push/WhatsApp viendront en phase B16.
 */
class RequestStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ServiceRequest $request) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // `database` alimente l'écran « Mes notifications » de l'espace client (F3.6),
        // en plus de l'e-mail. Arbitrage final par les réglages back-office (F7.2.l).
        return NotificationSettings::channels(
            NotificationEvent::REQUEST_STATUS_CHANGED,
            $notifiable,
            ['mail', 'database'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Le silence est ce qui inquiète le plus un client qui a payé ou déposé
        // un dossier. Cet e-mail existe pour le rompre : il dit où en est le
        // dossier, et surtout qui doit jouer le coup suivant.
        return BrandedMail::make()
            ->subject('Votre demande a avancé')
            ->preheader("Demande {$this->request->reference} : {$this->request->status->label()}.")
            ->eyebrow('Suivi de demande')
            ->heading('Votre demande a avancé.')
            ->intro('Nous vous tenons informé à chaque étape, sans que vous ayez à relancer. Voici le point sur votre dossier.')
            ->facts([
                'Référence' => $this->request->reference,
                'Service' => $this->request->service_type?->label(),
                'Nouveau statut' => $this->request->status->label(),
            ])
            ->action('Suivre ma demande', SpaceLink::requests($notifiable))
            ->note('L\'historique complet de votre demande — échanges, documents, décisions — reste consultable à tout moment depuis votre espace.')
            ->forRecipient($notifiable)
            ->reason('Vous recevez cet e-mail car vous suivez une demande sur Kaikun 360.')
            ->toMailMessage();
    }

    /**
     * Charge utile stockée dans la table `notifications` (canal `database`),
     * lue par NotificationResource pour l'écran client.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'request',
            'title' => 'Votre demande a avancé',
            'body' => "La demande « {$this->request->reference} » est passée au statut : {$this->request->status->label()}.",
            'action_url' => '/mon-espace/demandes',
        ];
    }
}
