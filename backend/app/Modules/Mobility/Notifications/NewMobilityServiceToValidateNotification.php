<?php

namespace App\Modules\Mobility\Notifications;

use App\Modules\Mobility\Models\MobilityService;
use App\Support\Mail\BrandedMail;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifie un agent qu'un départ programmé attend sa validation (F8.23).
 *
 * ⚠️ **L'urgence n'est pas la même que pour un véhicule** : un départ a une
 * DATE. Un trajet validé la veille au soir n'a plus personne à qui se vendre.
 * L'e-mail porte donc la date de départ dans ses faits — c'est elle qui dit à
 * l'agent dans quel ordre traiter sa file.
 */
class NewMobilityServiceToValidateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public MobilityService $mobilityService) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Même alerte interne « Nouvelle offre à valider » que les véhicules et les biens.
        return NotificationSettings::channels(
            NotificationEvent::RESOURCE_TO_VALIDATE,
            $notifiable,
            ['mail'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $trajet = $this->mobilityService->departure.' → '.$this->mobilityService->destination;

        return BrandedMail::make()
            ->subject('Nouveau départ programmé à valider')
            ->preheader("Départ {$this->mobilityService->reference} ({$trajet}) en attente de validation.")
            ->eyebrow('File de validation')
            ->tone('premium')
            ->heading('Un départ programmé attend votre validation.')
            ->intro('Un prestataire vient de programmer un départ. Il reste hors du catalogue tant qu\'il n\'est pas validé — et un départ validé après sa date ne se vend plus.')
            ->facts([
                'Référence' => $this->mobilityService->reference,
                'Trajet' => $trajet,
                'Type' => $this->mobilityService->type?->label(),
                'Départ le' => BrandedMail::date($this->mobilityService->departure_at),
                'Places' => $this->mobilityService->capacity,
            ])
            ->action('Ouvrir la file de validation', '/back-office/mobilite')
            ->forRecipient($notifiable)
            ->reason('Vous recevez cet e-mail en tant que membre de l\'équipe Kaikun 360.')
            ->toMailMessage();
    }
}
