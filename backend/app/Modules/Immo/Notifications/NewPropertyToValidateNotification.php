<?php

namespace App\Modules\Immo\Notifications;

use App\Modules\Immo\Models\Property;
use App\Support\Mail\BrandedMail;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notifie un agent qu'un nouveau bien attend sa validation.
 *
 * Canal mail (loggé en dev). Le push/WhatsApp viendra en phase B16.
 */
class NewPropertyToValidateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Property $property) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Alerte interne « Nouvelle offre à valider » (F7.2.l).
        return NotificationSettings::channels(
            NotificationEvent::RESOURCE_TO_VALIDATE,
            $notifiable,
            ['mail'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Alerte interne : c'est le point de contrôle qui fait tout notre
        // positionnement. Rien ne se publie sans être passé par ici.
        return BrandedMail::make()
            ->subject('Nouveau bien à valider')
            ->preheader("« {$this->property->title} » attend une décision de l'équipe.")
            ->eyebrow('File de validation')
            ->tone('premium')
            ->heading('Un bien attend votre validation.')
            ->intro('Un propriétaire vient de déposer une annonce. Elle reste invisible du public tant que le dossier n\'a pas été contrôlé.')
            ->facts([
                'Bien' => $this->property->title,
                'Type' => $this->property->type?->label(),
                'Prix demandé' => BrandedMail::money($this->property->price_xof),
                'Déposé le' => BrandedMail::date($this->property->created_at),
            ])
            ->action('Ouvrir la file de validation', '/back-office/validation')
            ->forRecipient($notifiable)
            ->reason('Vous recevez cet e-mail en tant que membre de l\'équipe Kaikun 360.')
            ->toMailMessage();
    }
}
