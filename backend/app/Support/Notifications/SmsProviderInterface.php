<?php

namespace App\Support\Notifications;

/**
 * Contrat d'un fournisseur d'envoi de SMS (B16.1).
 *
 * Le reste de l'application (canal de notification `sms`) ne dépend que de cette
 * abstraction, jamais d'un fournisseur concret : on peut passer de la
 * journalisation (dev) à Twilio (prod) sans toucher au code métier.
 */
interface SmsProviderInterface
{
    /**
     * Envoie `$message` au numéro `$to`. Renvoie vrai si l'envoi est accepté.
     */
    public function send(string $to, string $message): bool;
}
