<?php

namespace App\Support\Notifications;

use Illuminate\Support\Facades\Log;

/**
 * Fournisseur SMS de développement : journalise le message au lieu de l'envoyer
 * (aucun coût, aucun envoi réel). Fournisseur par défaut tant que Twilio n'est
 * pas configuré.
 */
class LogSmsProvider implements SmsProviderInterface
{
    public function send(string $to, string $message): bool
    {
        Log::info("SMS (log) → {$to} : {$message}");

        return true;
    }
}
