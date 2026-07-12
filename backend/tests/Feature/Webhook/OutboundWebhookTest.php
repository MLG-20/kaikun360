<?php

namespace Tests\Feature\Webhook;

use App\Jobs\SendWebhookJob;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Support\Webhooks\WebhookDispatcher;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * B18.1 — Webhooks sortants vers n8n : émission, désactivation, signature, câblage.
 */
class OutboundWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function enableWebhooks(): void
    {
        config()->set('services.n8n.enabled', true);
        config()->set('services.n8n.webhook_url', 'https://n8n.example.test/webhook/kaikun');
        config()->set('services.n8n.signing_secret', 'secret-partage');
    }

    public function test_emit_met_un_job_en_file_quand_active(): void
    {
        $this->enableWebhooks();
        Queue::fake();

        WebhookDispatcher::emit(WebhookDispatcher::QUOTE_RECEIVED, ['quote_reference' => 'QTE-X']);

        Queue::assertPushed(SendWebhookJob::class, function (SendWebhookJob $job) {
            return $job->payload['event'] === 'quote.received'
                && $job->payload['data']['quote_reference'] === 'QTE-X'
                && ! empty($job->payload['id'])
                && ! empty($job->payload['occurred_at']);
        });
    }

    public function test_emit_ne_fait_rien_quand_desactive(): void
    {
        // Config par défaut : intégration désactivée.
        Queue::fake();

        WebhookDispatcher::emit(WebhookDispatcher::QUOTE_RECEIVED, ['quote_reference' => 'QTE-X']);

        Queue::assertNothingPushed();
    }

    public function test_le_job_poste_un_corps_signe_en_hmac(): void
    {
        $this->enableWebhooks();
        Http::fake();

        $payload = [
            'id' => 'delivery-1',
            'event' => 'quote.received',
            'occurred_at' => '2026-07-12T18:00:00+00:00',
            'data' => ['quote_reference' => 'QTE-X'],
        ];

        (new SendWebhookJob($payload))->handle();

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $expected = hash_hmac('sha256', $body, 'secret-partage');

        Http::assertSent(function ($request) use ($expected) {
            return $request->url() === 'https://n8n.example.test/webhook/kaikun'
                && $request->hasHeader('X-Kaikun-Signature', $expected)
                && $request->header('X-Kaikun-Event')[0] === 'quote.received'
                && $request->header('X-Kaikun-Delivery')[0] === 'delivery-1';
        });
    }

    public function test_la_creation_dun_devis_emet_un_webhook(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->enableWebhooks();
        Http::fake();

        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);
        $request = ServiceRequest::factory()->create();

        Sanctum::actingAs($agent);
        $this->postJson("/api/v1/requests/{$request->id}/quotes", ['amount_xof' => 500_000])
            ->assertCreated();

        // La file étant synchrone en test, le job s'exécute et l'appel HTTP part.
        Http::assertSent(fn ($req) => $req->header('X-Kaikun-Event')[0] === 'quote.received');
    }
}
