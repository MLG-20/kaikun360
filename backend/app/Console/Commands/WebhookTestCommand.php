<?php

namespace App\Console\Commands;

use App\Jobs\SendWebhookJob;
use App\Support\Webhooks\WebhookDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * B18.1 — Déclenche un webhook de TEST vers n8n.
 *
 * Permet à l'équipe n8n de câbler et vérifier ses scénarios sans avoir à créer de
 * vraies réservations/devis : la commande envoie une charge utile fictive mais au
 * format réel. Elle contourne volontairement le drapeau `enabled` (pour tester
 * avant activation globale) mais exige quand même une URL n8n configurée.
 *
 * Exemples :
 *   php artisan webhook:test                    # liste les événements disponibles
 *   php artisan webhook:test quote.received     # envoie un exemple à n8n
 */
class WebhookTestCommand extends Command
{
    protected $signature = 'webhook:test {event? : Nom de l\'événement à simuler}';

    protected $description = 'Envoie un webhook de test (charge fictive) vers n8n pour valider les scénarios.';

    /**
     * Exemples de charge par événement (mêmes champs qu'en production).
     *
     * @return array<string, array<string, mixed>>
     */
    private function samples(): array
    {
        $user = ['name' => 'Awa Diop', 'phone' => '+221770000000'];

        return [
            WebhookDispatcher::BOOKING_CONFIRMED => [
                'booking_reference' => 'BKG-DEMO1234',
                'bookable_type' => 'Stay',
                'amount_xof' => 75000,
                'user' => $user,
            ],
            WebhookDispatcher::QUOTE_RECEIVED => [
                'quote_reference' => 'QTE-DEMO5678',
                'request_reference' => 'REQ-DEMO9012',
                'amount_xof' => 1250000,
                'user' => $user,
            ],
            WebhookDispatcher::DOCUMENT_REQUIRED => [
                'document_type' => 'cni',
                'note' => 'Merci de fournir une pièce d\'identité lisible.',
                'user' => $user,
            ],
            WebhookDispatcher::REQUEST_STATUS_CHANGED => [
                'request_reference' => 'REQ-DEMO9012',
                'from' => 'verification',
                'to' => 'visite',
                'user' => $user,
            ],
        ];
    }

    public function handle(): int
    {
        $samples = $this->samples();
        $event = $this->argument('event');

        if ($event === null) {
            $this->info('Événements disponibles :');
            foreach (array_keys($samples) as $name) {
                $this->line("  • {$name}");
            }
            $this->newLine();
            $this->line('Usage : <comment>php artisan webhook:test <event></comment>');

            return self::SUCCESS;
        }

        if (! array_key_exists($event, $samples)) {
            $this->error("Événement inconnu : {$event}");
            $this->line('Utilisez `php artisan webhook:test` pour lister les événements.');

            return self::FAILURE;
        }

        $url = config('services.n8n.webhook_url');
        if (empty($url)) {
            $this->error('Aucune URL n8n configurée (N8N_WEBHOOK_URL). Renseignez-la dans .env avant de tester.');

            return self::FAILURE;
        }

        // Envoi synchrone pour un retour immédiat (on ne dépend pas d'un worker ici).
        SendWebhookJob::dispatchSync([
            'id' => (string) Str::uuid(),
            'event' => $event,
            'occurred_at' => now()->toIso8601String(),
            'data' => $samples[$event],
        ]);

        $this->info("Webhook de test « {$event} » envoyé à {$url}.");

        return self::SUCCESS;
    }
}
