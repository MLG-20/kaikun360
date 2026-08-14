<?php

namespace App\Notifications;

use App\Models\WaitlistEntry;
use App\Support\Mail\BrandedMail;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifie l'équipe qu'un prospect vient de s'inscrire à la liste d'attente
 * avant ouverture officielle (2026-08-14).
 *
 * Même mécanique que {@see NewContactMessageNotification} : le dépôt est
 * public (throttle 10/min) et il n'existe pas encore d'écran back-office pour
 * consulter les inscriptions (reporté) — cet e-mail est donc, pour l'instant,
 * le SEUL moyen de savoir qu'un prospect s'est inscrit.
 */
class NewWaitlistEntryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public WaitlistEntry $entry) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return NotificationSettings::channels(
            NotificationEvent::WAITLIST_ENTRY,
            $notifiable,
            ['mail'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return BrandedMail::make()
            ->subject('Nouvelle inscription à la liste d’attente')
            ->preheader($this->entry->category->label().' — '.$this->entry->name)
            ->eyebrow('Liste d’attente')
            ->heading('Un prospect vient de s’inscrire.')
            ->intro('Un visiteur vient de laisser ses coordonnées sur la liste d’attente avant ouverture. Il n’a pas de compte : le suivi se fait pour l’instant directement, en dehors de la plateforme.')
            ->facts(array_filter([
                'Catégorie' => $this->entry->category->label(),
                'Nom' => $this->entry->name,
                'Téléphone' => $this->entry->phone,
                'E-mail' => $this->entry->email,
                'Ville' => $this->entry->city,
                'Reçu le' => BrandedMail::date($this->entry->created_at),
            ]))
            ->note($this->entry->precisions ?: 'Aucune précision laissée.')
            ->forRecipient($notifiable)
            ->reason('Vous recevez cet e-mail en tant que membre de l\'équipe Kaikun 360.')
            ->toMailMessage();
    }
}
