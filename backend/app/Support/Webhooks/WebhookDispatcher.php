<?php

namespace App\Support\Webhooks;

use App\Jobs\SendWebhookJob;
use Illuminate\Support\Str;

/**
 * B18.1 — Émission des webhooks sortants (métier → n8n).
 *
 * Point d'entrée UNIQUE : le code métier appelle `WebhookDispatcher::emit()` avec
 * le nom de l'événement et ses données. Le dispatcher enveloppe le tout dans un
 * format stable et délègue l'envoi réseau à {@see SendWebhookJob} (asynchrone,
 * signé, ré-essayé).
 *
 * Contrat d'enveloppe (ce que n8n reçoit) :
 *   {
 *     "id":          "<uuid unique de livraison — sert à dédupliquer>",
 *     "event":       "<nom.de.l.evenement>",
 *     "occurred_at": "<ISO 8601>",
 *     "data":        { … charge utile propre à l'événement … }
 *   }
 *
 * Séparation des responsabilités : le backend dit CE QUI s'est passé (l'événement) ;
 * n8n décide QUOI EN FAIRE (envoyer un WhatsApp, relancer, prévenir un agent…). On
 * ne met jamais de logique métier dans n8n, ni d'orchestration dans le backend.
 *
 * Le catalogue des événements est documenté dans `WEBHOOKS.md` (contrat pour
 * l'équipe n8n).
 */
class WebhookDispatcher
{
    // Noms d'événements connus (constantes = source de vérité + anti-typo).
    public const BOOKING_CONFIRMED = 'booking.confirmed';
    public const QUOTE_RECEIVED = 'quote.received';
    public const DOCUMENT_REQUIRED = 'document.required';
    public const REQUEST_STATUS_CHANGED = 'request.status_changed';

    /**
     * Émet un événement vers n8n (no-op tant que l'intégration n'est pas activée).
     *
     * @param  array<string, mixed>  $data
     */
    public static function emit(string $event, array $data): void
    {
        // Désactivé (dev/test ou avant fourniture de l'URL) → on ne fait rien.
        if (! config('services.n8n.enabled') || empty(config('services.n8n.webhook_url'))) {
            return;
        }

        SendWebhookJob::dispatch([
            'id' => (string) Str::uuid(),
            'event' => $event,
            'occurred_at' => now()->toIso8601String(),
            'data' => $data,
        ]);
    }
}
