<?php

namespace App\Modules\TeamBuilding\Notifications;

use App\Modules\TeamBuilding\Models\TeamBuildingQuote;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notifie l'entreprise qu'un devis de team building lui a été envoyé.
 *
 * Deux canaux :
 *   - `mail` (loggé en dev) — trace écrite pour l'entreprise ;
 *   - `database` — alimente la **cloche** + l'écran « Notifications » de l'espace
 *     entreprise (F6, cahier §5 « Notifications = Tous »). Sans lui, l'entreprise
 *     ne verrait jamais in-app qu'un devis l'attend et devrait ouvrir « Mes
 *     demandes » à l'aveugle.
 *
 * Le push/WhatsApp viendra en phase B16.
 */
class TeamBuildingQuoteSentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TeamBuildingQuote $quote) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Arbitrage par les réglages back-office (F7.2.l).
        return NotificationSettings::channels(
            NotificationEvent::TEAM_BUILDING_QUOTE,
            $notifiable,
            ['mail', 'database'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre devis team building — Kaikun 360')
            ->line("Un devis « {$this->quote->reference} » vous a été envoyé.")
            ->line("Montant total : {$this->quote->total_xof} XOF.")
            ->line('Connectez-vous pour l\'accepter.');
    }

    /**
     * Charge utile du canal `database` (cloche + écran « Notifications »).
     *
     * `action_url` mène au détail de la demande dans l'espace entreprise, où le
     * devis est consultable et acceptable (F6).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'team_building',
            'title' => 'Votre devis team building est prêt',
            'body' => "Le devis « {$this->quote->reference} » vous a été envoyé (total : {$this->quote->total_xof} XOF).",
            'action_url' => '/espace-entreprise/demandes/'.$this->quote->request_id,
        ];
    }
}
