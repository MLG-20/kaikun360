<?php

namespace App\Support\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fournisseur SMS Twilio (B16.1).
 *
 * Envoi via l'API REST Twilio (Basic Auth SID:token). Les identifiants viennent
 * de la configuration (`config/services.sms`), jamais du code. Activé en posant
 * `SMS_PROVIDER=twilio` ; les clés réelles sont fournies par le client.
 */
class TwilioSmsProvider implements SmsProviderInterface
{
    public function __construct(
        private readonly ?string $sid,
        private readonly ?string $token,
        private readonly ?string $from,
    ) {
    }

    public function send(string $to, string $message): bool
    {
        if (empty($this->sid) || empty($this->token)) {
            Log::warning('SMS Twilio non configuré : message non envoyé.');

            return false;
        }

        $response = Http::asForm()
            ->withBasicAuth($this->sid, $this->token)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json", [
                'To' => $to,
                'From' => $this->from,
                'Body' => $message,
            ]);

        return $response->successful();
    }
}
