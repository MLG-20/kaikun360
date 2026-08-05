<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use App\Support\Mail\BrandedMail;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifie l'équipe qu'un message a été déposé sur la page Contact (F8.15.c bis).
 *
 * POURQUOI ELLE EXISTE
 * --------------------
 * F8.15.c a donné un écran à ce courrier, mais rien n'avertissait de son
 * arrivée : il fallait penser à ouvrir l'onglet. Le seul relais prévu était le
 * webhook n8n (`contact.received`), **non configuré** — donc silencieux. Les
 * trois autres files d'attente (demandes, offres à valider, team building,
 * chantiers) alertent l'équipe depuis longtemps ; la page Contact, l'un des
 * canaux de conversion prioritaires du cahier des charges, ne le faisait pas.
 *
 * ⚠️ **L'e-mail porte le message ENTIER**, pas seulement un avis d'arrivée. Le
 * dépôt est ouvert à tout visiteur, sans compte : la plupart de ces messages se
 * règlent en répondant directement depuis sa boîte. Obliger à ouvrir le
 * back-office pour lire trois lignes ferait exactement ce qu'on vient de
 * corriger — du courrier qui attend.
 *
 * ⚠️ **Coupable désigné en cas de bruit** : l'endpoint est public (throttle
 * 10/min). L'interrupteur `contact_message` du back-office éteint cette alerte
 * sans toucher à l'écran, qui reste la source de vérité.
 */
class NewContactMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ContactMessage $message) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Alerte interne ; arbitrage par les réglages back-office (F7.2.l).
        return NotificationSettings::channels(
            NotificationEvent::CONTACT_MESSAGE,
            $notifiable,
            ['mail'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return BrandedMail::make()
            ->subject('Nouveau message depuis la page Contact')
            ->preheader($this->message->subject ?: 'Message de '.$this->message->name)
            ->eyebrow('Page Contact')
            ->heading('Un visiteur attend une réponse.')
            ->intro('Un message vient d\'arriver depuis la page Contact du site. Son auteur n\'a le plus souvent pas de compte : il n\'y a pas de fil de discussion à ouvrir, on lui répond directement.')
            ->facts([
                'De' => $this->message->name,
                'E-mail' => $this->message->email,
                'Sujet' => $this->message->subject ?: 'Sans sujet',
                'Reçu le' => BrandedMail::date($this->message->created_at),
            ])
            // Le message en clair : la plupart se règlent d'une réponse directe,
            // sans jamais ouvrir le back-office. `note` le détache visuellement
            // du texte de l'e-mail — c'est la parole du visiteur, pas la nôtre.
            ->note($this->message->message)
            ->action('Ouvrir le courrier', '/back-office/messages')
            ->forRecipient($notifiable)
            ->reason('Vous recevez cet e-mail en tant que membre de l\'équipe Kaikun 360.')
            ->toMailMessage();
    }
}
