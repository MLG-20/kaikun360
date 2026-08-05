<?php

namespace App\Modules\Build\Notifications;

use App\Modules\Build\Models\ConstructionRequest;
use App\Support\Mail\BrandedMail;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifie l'équipe back-office d'une nouvelle demande de chantier (F8.15.b).
 *
 * Canal mail (loggé en dev), comme les deux autres alertes de file d'attente.
 */
class NewConstructionRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ConstructionRequest $request) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Alerte interne ; arbitrage par les réglages back-office (F7.2.l).
        return NotificationSettings::channels(
            NotificationEvent::CONSTRUCTION_REQUEST,
            $notifiable,
            ['mail'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return BrandedMail::make()
            ->subject('Nouvelle demande de chantier')
            ->preheader("Demande {$this->request->reference} — {$this->request->surface_m2} m² à {$this->request->city}.")
            ->eyebrow('Kaikun Build')
            ->heading('Un chantier attend un chiffrage.')
            ->intro('Une demande de construction vient d\'arriver, déjà cadrée par le simulateur : objectif, surface, finition et localisation sont renseignés. L\'estimation ci-dessous est celle qu\'a vue le client — le devis ferme reste à établir.')
            ->facts([
                'Référence' => $this->request->reference,
                'Objectif' => $this->request->objective?->label(),
                'Surface' => $this->request->surface_m2.' m²',
                'Finition' => $this->request->finish_level?->label(),
                'Ville' => $this->request->city,
                'Budget du client' => BrandedMail::money($this->request->budget_xof),
                'Estimation Kaikun' => BrandedMail::money($this->request->estimated_cost_xof),
            ])
            ->action('Ouvrir le dossier', '/back-office/construction')
            ->forRecipient($notifiable)
            ->reason('Vous recevez cet e-mail en tant que membre de l\'équipe Kaikun 360.')
            ->toMailMessage();
    }
}
