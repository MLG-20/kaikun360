<?php

namespace App\Modules\TeamBuilding\Notifications;

use App\Modules\TeamBuilding\Models\TeamBuildingRequest;
use App\Support\Mail\BrandedMail;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notifie l'équipe back-office d'une nouvelle demande de team building.
 *
 * Canal mail (loggé en dev). Le push/WhatsApp viendra en phase B16.
 */
class NewTeamBuildingRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TeamBuildingRequest $request) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Alerte interne ; arbitrage par les réglages back-office (F7.2.l).
        return NotificationSettings::channels(
            NotificationEvent::TEAM_BUILDING_REQUEST,
            $notifiable,
            ['mail'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return BrandedMail::make()
            ->subject('Nouvelle demande team building')
            ->preheader("Demande {$this->request->reference} — {$this->request->participants} participants à {$this->request->city}.")
            ->eyebrow('Demande entreprise')
            ->heading('Une entreprise attend une proposition.')
            ->intro('Une demande de team building vient d\'arriver. Ces dossiers se jouent souvent sur la rapidité de la première réponse : l\'entreprise consulte rarement un seul prestataire.')
            ->facts([
                'Référence' => $this->request->reference,
                'Participants' => (string) $this->request->participants,
                'Ville' => $this->request->city,
                'Du' => BrandedMail::date($this->request->start_date),
                'Au' => BrandedMail::date($this->request->end_date),
                'Budget indiqué' => BrandedMail::money($this->request->budget_xof),
            ])
            ->action('Ouvrir la demande', '/back-office/team-building')
            ->forRecipient($notifiable)
            ->reason('Vous recevez cet e-mail en tant que membre de l\'équipe Kaikun 360.')
            ->toMailMessage();
    }
}
