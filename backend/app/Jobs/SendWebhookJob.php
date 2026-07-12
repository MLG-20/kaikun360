<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

/**
 * B18.1 — Envoi asynchrone d'un webhook sortant vers n8n.
 *
 * Le corps est signé en HMAC-SHA256 (en-tête `X-Kaikun-Signature`) avec le secret
 * partagé, exactement comme notre webhook PayTech entrant mais dans l'autre sens :
 * n8n recalcule la signature sur le corps BRUT reçu et la compare pour authentifier
 * l'appel.
 *
 * L'envoi passe par la file d'attente et est ré-essayé en cas d'échec réseau ou de
 * réponse non-2xx : une indisponibilité momentanée de n8n ne perd aucun événement.
 */
class SendWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Nombre de tentatives avant abandon. */
    public int $tries = 5;

    /** Délais (secondes) entre les tentatives — backoff progressif. */
    public array $backoff = [10, 30, 60, 120];

    /**
     * @param  array<string, mixed>  $payload  Enveloppe {id, event, occurred_at, data}.
     */
    public function __construct(public array $payload)
    {
    }

    public function handle(): void
    {
        $url = config('services.n8n.webhook_url');

        // Garde-fou : rien à envoyer si l'URL n'est pas (ou plus) configurée.
        if (empty($url)) {
            return;
        }

        // On signe le corps EXACT qui sera transmis (mêmes octets que n8n recevra).
        $body = json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $body, (string) config('services.n8n.signing_secret'));

        Http::withHeaders([
            'X-Kaikun-Event' => $this->payload['event'] ?? '',
            'X-Kaikun-Delivery' => $this->payload['id'] ?? '',
            'X-Kaikun-Signature' => $signature,
        ])
            ->withBody($body, 'application/json')
            ->post($url)
            ->throw(); // 4xx/5xx → exception → ré-essai par la file
    }
}
