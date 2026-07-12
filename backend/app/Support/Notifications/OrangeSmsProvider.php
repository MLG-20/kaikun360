<?php

namespace App\Support\Notifications;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fournisseur SMS Orange / Sonatel (B18.2).
 *
 * Adapté au Sénégal : meilleure délivrabilité et tarif local, expéditeur nommé
 * possible (« KAIKUN360 »). Utilise l'API SMS d'Orange (standard GSMA OneAPI) :
 *
 *   1. Authentification OAuth2 « client_credentials » (Basic Auth client_id:secret)
 *      → jeton d'accès mis en cache jusqu'à son expiration.
 *   2. Envoi POST sur `.../smsmessaging/v1/outbound/{senderAddress}/requests`.
 *
 * Tout est piloté par `config/services.sms.orange` (aucune valeur en dur) ; activé
 * en posant `SMS_PROVIDER=orange`. Les identifiants sont obtenus en souscrivant la
 * « SMS API » à l'application sur developer.orange.com (un quota de test/sandbox
 * est disponible). Comme pour PayTech, le code est testé via `Http::fake` sans clés
 * réelles.
 */
class OrangeSmsProvider implements SmsProviderInterface
{
    /** Clé de cache du jeton d'accès OAuth2. */
    private const TOKEN_CACHE_KEY = 'kaikun.orange_sms.token';

    public function __construct(
        private readonly ?string $clientId,
        private readonly ?string $clientSecret,
        private readonly string $baseUrl,
        private readonly string $tokenUrl,
        private readonly ?string $senderAddress,
        private readonly ?string $senderName,
    ) {
    }

    public function send(string $to, string $message): bool
    {
        if (empty($this->clientId) || empty($this->clientSecret) || empty($this->senderAddress)) {
            Log::warning('SMS Orange non configuré : message non envoyé.');

            return false;
        }

        $token = $this->accessToken();
        if ($token === null) {
            return false; // échec d'authentification déjà journalisé
        }

        // L'expéditeur et le destinataire sont au format `tel:+indicatif...`.
        $sender = 'tel:'.$this->senderAddress;
        $endpoint = rtrim($this->baseUrl, '/')
            .'/smsmessaging/v1/outbound/'.rawurlencode($sender).'/requests';

        $payload = [
            'outboundSMSMessageRequest' => [
                'address' => 'tel:'.$to,
                'senderAddress' => $sender,
                'outboundSMSTextMessage' => ['message' => $message],
            ],
        ];

        // Expéditeur alphanumérique optionnel (ex. « KAIKUN360 »).
        if (! empty($this->senderName)) {
            $payload['outboundSMSMessageRequest']['senderName'] = $this->senderName;
        }

        $response = Http::withToken($token)->acceptJson()->post($endpoint, $payload);

        if (! $response->successful()) {
            Log::error('Échec envoi SMS Orange.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Jeton d'accès OAuth2, mis en cache jusqu'à peu avant son expiration.
     * Ne met JAMAIS en cache un échec (pour retenter au prochain envoi).
     */
    private function accessToken(): ?string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (! empty($cached)) {
            return $cached;
        }

        $response = Http::asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->acceptJson()
            ->post($this->tokenUrl, ['grant_type' => 'client_credentials']);

        if (! $response->successful()) {
            Log::error('Échec authentification Orange SMS.', ['status' => $response->status()]);

            return null;
        }

        $token = $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 3600);

        // Marge de 60 s pour éviter d'utiliser un jeton qui vient d'expirer.
        Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addSeconds(max(60, $expiresIn - 60)));

        return $token;
    }
}
