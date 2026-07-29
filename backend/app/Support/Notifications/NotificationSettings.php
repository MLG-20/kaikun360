<?php

namespace App\Support\Notifications;

use App\Support\Settings;

/**
 * Point de décision UNIQUE des canaux de notification (F7.2.l).
 *
 * Avant cette tranche, chaque notification codait ses canaux en dur dans son
 * `via()` : impossible pour l'équipe de couper le SMS (canal facturé à l'envoi)
 * ou de calmer un événement trop bavard sans redéployer. Le CDC §6 range
 * pourtant « notifications » dans le module *Paramètres*.
 *
 * Les notifications d'exploitation appellent donc {@see self::channels()} au
 * lieu de retourner un tableau littéral. Trois règles, dans cet ordre :
 *
 *  1. **Événement coupé** → aucun canal. Laravel n'envoie alors rien du tout
 *     (un `via()` vide court-circuite l'envoi), pas même l'entrée en base.
 *  2. **Canal coupé globalement** (`notifications.sms_enabled`,
 *     `notifications.email_enabled`) → le canal est retiré de la liste.
 *  3. **SMS sans numéro** → retiré aussi. Cette vérification vivait dupliquée
 *     dans plusieurs `via()` ; elle est désormais faite ici, une seule fois.
 *
 * Le canal `database` n'est jamais coupé : il n'a aucun coût, il alimente
 * l'écran « Mes notifications » et il constitue la trace de ce qui a été
 * signalé à l'utilisateur. Seule la coupure de l'ÉVÉNEMENT le supprime.
 *
 * ⚠️ Hors de portée volontairement : les codes de vérification et la 2FA
 * (voir la note de {@see NotificationEvent}).
 */
class NotificationSettings
{
    /**
     * Canaux effectivement retenus pour un envoi.
     *
     * @param  list<string>  $desired  Canaux souhaités par la notification.
     * @return list<string>
     */
    public static function channels(NotificationEvent $event, object $notifiable, array $desired): array
    {
        if (! self::eventEnabled($event)) {
            return [];
        }

        return array_values(array_filter($desired, function (string $channel) use ($notifiable) {
            return match ($channel) {
                'sms' => self::smsEnabled() && ! empty($notifiable->phone),
                'mail' => self::emailEnabled(),
                default => true,
            };
        }));
    }

    /**
     * L'événement est-il actif ? Un événement absent de la configuration
     * enregistrée est actif par défaut (voir {@see NotificationEvent}).
     */
    public static function eventEnabled(NotificationEvent $event): bool
    {
        $events = Settings::get('notifications.events', []);

        if (! is_array($events) || ! array_key_exists($event->value, $events)) {
            return true;
        }

        return filter_var($events[$event->value], FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Le canal SMS est-il ouvert ? (Canal payant — première coupure utile.)
     */
    public static function smsEnabled(): bool
    {
        return filter_var(Settings::get('notifications.sms_enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Le canal e-mail est-il ouvert ?
     */
    public static function emailEnabled(): bool
    {
        return filter_var(Settings::get('notifications.email_enabled', true), FILTER_VALIDATE_BOOLEAN);
    }
}
