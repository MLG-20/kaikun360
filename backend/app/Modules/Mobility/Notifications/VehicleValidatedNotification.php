<?php

namespace App\Modules\Mobility\Notifications;

use App\Modules\Mobility\Models\Vehicle;
use App\Support\Mail\BrandedMail;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notifie le prestataire que son véhicule est validé et visible dans la recherche.
 *
 * Canal mail (loggé en dev). Le push/WhatsApp viendra en phase B16.
 */
class VehicleValidatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Vehicle $vehicle) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Même événement « Offre validée » que le bien immobilier (F7.2.l).
        return NotificationSettings::channels(
            NotificationEvent::RESOURCE_VALIDATED,
            $notifiable,
            ['mail'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return BrandedMail::make()
            ->subject('Votre véhicule est en ligne')
            ->preheader("Véhicule {$this->vehicle->reference} validé : il apparaît dans la recherche.")
            ->eyebrow('Publication')
            ->tone('success')
            ->heading('Votre véhicule est en ligne.')
            ->intro('Nous avons vérifié la conformité de votre véhicule et de vos documents. Il apparaît désormais dans les résultats de recherche et peut être réservé.')
            ->facts([
                'Référence' => $this->vehicle->reference,
                'Type' => $this->vehicle->type?->label(),
                'Validé le' => BrandedMail::date(now()),
            ])
            ->action('Gérer mes offres', '/espace-prestataire/offres')
            ->note('Pensez à tenir vos disponibilités à jour : un calendrier juste évite les annulations, qui pèsent sur votre note et sur vos revenus.')
            ->forRecipient($notifiable)
            ->reason('Vous recevez cet e-mail car vous proposez un véhicule sur Kaikun 360.')
            ->toMailMessage();
    }
}
