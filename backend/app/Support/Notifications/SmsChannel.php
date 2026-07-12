<?php

namespace App\Support\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Canal de notification Laravel « sms » (B16.1).
 *
 * Délègue l'envoi au SmsProviderInterface configuré. Une notification qui
 * souhaite passer par SMS déclare `sms` dans `via()` et expose `toSms()`
 * renvoyant le texte du message. Le destinataire est résolu via
 * `routeNotificationForSms()` (numéro de téléphone).
 */
class SmsChannel
{
    public function __construct(private readonly SmsProviderInterface $provider)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $to = $notifiable->routeNotificationFor('sms', $notification)
            ?? ($notifiable->phone ?? null);

        if (empty($to)) {
            return;
        }

        $this->provider->send($to, $notification->toSms($notifiable));
    }
}
