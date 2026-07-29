<?php

namespace App\Notifications;

use App\Models\Message;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Prévient un participant qu'il a reçu un nouveau message (messagerie F3.7).
 *
 * Canal `database` UNIQUEMENT : la messagerie est temps réel côté application
 * (l'utilisateur y répond directement) ; inutile de doubler chaque message
 * d'un e-mail ou d'un SMS. La notification alimente la cloche + l'écran
 * « Mes notifications » (F3.6) et pointe vers le fil concerné.
 */
class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Message $message)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Canal unique, mais l'équipe peut couper l'événement (F7.2.l).
        return NotificationSettings::channels(
            NotificationEvent::NEW_MESSAGE,
            $notifiable,
            ['database'],
        );
    }

    /**
     * Charge utile du canal `database` (cloche + écran « Mes notifications »).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $senderName = $this->message->sender?->name ?? 'Un correspondant';
        // Aperçu court du corps, tronqué proprement pour la ligne de notification.
        $preview = Str::limit((string) $this->message->body, 80);

        return [
            'category' => 'message',
            'title' => "Nouveau message de {$senderName}",
            'body' => $preview,
            'action_url' => '/mon-espace/messages/'.$this->message->conversation_id,
        ];
    }
}
