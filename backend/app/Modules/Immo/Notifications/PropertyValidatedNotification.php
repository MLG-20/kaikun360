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
 * Informe le propriétaire que son bien a été validé et publié.
 */
class PropertyValidatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Property $property) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Arbitrage par les réglages back-office (F7.2.l) : événement « Offre
        // validée », commun aux biens et aux véhicules.
        return NotificationSettings::channels(
            NotificationEvent::RESOURCE_VALIDATED,
            $notifiable,
            ['mail'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Moment positif du parcours propriétaire : on le célèbre franchement
        // (ton « succès », vert), et on enchaîne aussitôt sur ce qui augmente
        // les chances de louer — le propriétaire est ici pour un résultat.
        return BrandedMail::make()
            ->subject('Votre bien est en ligne')
            ->preheader("« {$this->property->title} » est publié et visible par les visiteurs.")
            ->eyebrow('Publication')
            ->tone('success')
            ->heading('Votre bien est en ligne.')
            ->intro(
                'Notre équipe a vérifié votre dossier : votre annonce est publiée et visible par tous les visiteurs de Kaikun 360. Elle porte désormais le sceau « vérifié », le repère que nos utilisateurs recherchent.'
            )
            ->facts([
                'Bien' => $this->property->title,
                'Type' => $this->property->type?->label(),
                'Prix affiché' => BrandedMail::money($this->property->price_xof),
                'Publié le' => BrandedMail::date($this->property->published_at ?? now()),
            ])
            ->action('Voir mon annonce', '/espace-proprietaire/biens/'.$this->property->id)
            ->steps([
                'Vérifiez une dernière fois vos photos et votre description : ce sont elles qui déclenchent les demandes.',
                'Activez vos notifications pour être prévenu dès la première demande de visite.',
                'Répondez vite : les propriétaires qui répondent dans la journée concluent nettement plus souvent.',
            ], 'Pour tirer le meilleur de votre annonce')
            ->forRecipient($notifiable)
            ->reason('Vous recevez cet e-mail car vous avez déposé un bien sur Kaikun 360.')
            ->toMailMessage();
    }
}
