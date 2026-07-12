<?php

namespace Tests\Feature\Notification;

use App\Support\Notifications\LogSmsProvider;
use App\Support\Notifications\OrangeSmsProvider;
use App\Support\Notifications\SmsProviderInterface;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * B18.2 — Fournisseur SMS Orange / Sonatel.
 *
 * Testé via `Http::fake` (aucune clé réelle requise), exactement comme PayTech :
 * on valide l'authentification OAuth2, le format d'envoi et les cas d'échec.
 */
class OrangeSmsProviderTest extends TestCase
{
    private function provider(): OrangeSmsProvider
    {
        return new OrangeSmsProvider(
            clientId: 'cid',
            clientSecret: 'secret',
            baseUrl: 'https://api.orange.com',
            tokenUrl: 'https://api.orange.com/oauth/v3/token',
            senderAddress: '+221770000000',
            senderName: 'KAIKUN360',
        );
    }

    public function test_envoie_un_sms_apres_authentification_oauth2(): void
    {
        Http::fake([
            'api.orange.com/oauth/*' => Http::response(['access_token' => 'tok-123', 'expires_in' => 3600]),
            'api.orange.com/smsmessaging/*' => Http::response([], 201),
        ]);

        $ok = $this->provider()->send('+221781111111', 'Votre code est 123456');

        $this->assertTrue($ok);

        // Authentification : Basic Auth + grant client_credentials.
        Http::assertSent(fn ($r) => str_contains($r->url(), '/oauth/v3/token')
            && $r['grant_type'] === 'client_credentials');

        // Envoi : jeton Bearer + payload au format Orange (OneAPI).
        Http::assertSent(function ($r) {
            return str_contains($r->url(), '/smsmessaging/v1/outbound/')
                && $r->hasHeader('Authorization', 'Bearer tok-123')
                && $r['outboundSMSMessageRequest']['address'] === 'tel:+221781111111'
                && $r['outboundSMSMessageRequest']['senderAddress'] === 'tel:+221770000000'
                && $r['outboundSMSMessageRequest']['outboundSMSTextMessage']['message'] === 'Votre code est 123456'
                && $r['outboundSMSMessageRequest']['senderName'] === 'KAIKUN360';
        });
    }

    public function test_retourne_false_si_non_configure(): void
    {
        Http::fake();

        $provider = new OrangeSmsProvider(null, null, 'https://api.orange.com', 'https://api.orange.com/oauth/v3/token', null, null);

        $this->assertFalse($provider->send('+221781111111', 'test'));
        Http::assertNothingSent();
    }

    public function test_retourne_false_si_authentification_echoue(): void
    {
        Http::fake([
            'api.orange.com/oauth/*' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        $this->assertFalse($this->provider()->send('+221781111111', 'test'));

        // On n'a pas tenté d'envoyer le SMS sans jeton valide.
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/smsmessaging/'));
    }

    public function test_le_binding_resout_orange_selon_la_config(): void
    {
        config()->set('services.sms.provider', 'orange');
        config()->set('services.sms.orange.client_id', 'cid');
        config()->set('services.sms.orange.client_secret', 'secret');
        config()->set('services.sms.orange.sender_address', '+221770000000');

        app()->forgetInstance(SmsProviderInterface::class);
        $this->assertInstanceOf(OrangeSmsProvider::class, app(SmsProviderInterface::class));

        // Par défaut (log), on retombe sur le fournisseur de journalisation.
        config()->set('services.sms.provider', 'log');
        app()->forgetInstance(SmsProviderInterface::class);
        $this->assertInstanceOf(LogSmsProvider::class, app(SmsProviderInterface::class));
    }
}
