<?php

namespace App\Modules\Mobility\Notifications;

use App\Modules\Mobility\Models\Vehicle;
use App\Support\Mail\BrandedMail;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifie un agent qu'un nouveau véhicule attend sa validation.
 *
 * Canal mail (loggé en dev). Le push/WhatsApp viendra en phase B16.
 */
class NewVehicleToValidateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Vehicle $vehicle) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Même alerte interne « Nouvelle offre à valider » que les biens (F7.2.l).
        return NotificationSettings::channels(
            NotificationEvent::RESOURCE_TO_VALIDATE,
            $notifiable,
            ['mail'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return BrandedMail::make()
            ->subject('Nouveau véhicule à valider')
            ->preheader("Véhicule {$this->vehicle->reference} en attente de contrôle de conformité.")
            ->eyebrow('File de validation')
            ->tone('premium')
            ->heading('Un véhicule attend votre validation.')
            ->intro('Un prestataire vient de déposer un véhicule. Il reste hors des résultats de recherche tant que sa conformité n\'a pas été vérifiée (assurance, visite technique, carte grise).')
            ->facts([
                'Référence' => $this->vehicle->reference,
                'Type' => $this->vehicle->type?->label(),
                'Déposé le' => BrandedMail::date($this->vehicle->created_at),
            ])
            ->action('Ouvrir la file de validation', '/back-office/mobilite')
            ->forRecipient($notifiable)
            ->reason('Vous recevez cet e-mail en tant que membre de l\'équipe Kaikun 360.')
            ->toMailMessage();
    }
}
