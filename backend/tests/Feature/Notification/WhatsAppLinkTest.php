<?php

namespace Tests\Feature\Notification;

use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests B16.3 : lien WhatsApp click-to-chat contextuel (message prérempli).
 */
class WhatsAppLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_lien_par_defaut_est_genere(): void
    {
        $this->getJson('/api/v1/whatsapp/link')
            ->assertOk()
            ->assertJsonPath('data.url', fn ($url) => str_starts_with($url, 'https://wa.me/'))
            ->assertJsonPath('data.message', 'Bonjour, je souhaite un renseignement sur Kaikun 360.');
    }

    public function test_le_message_est_contextualise(): void
    {
        // Numéro de support paramétré au back-office (B13.4).
        Settings::set('support.phone', '+221 77 123 45 67');

        $response = $this->getJson('/api/v1/whatsapp/link?'.http_build_query([
            'subject' => 'Réservation',
            'reference' => 'BK-123',
        ]))->assertOk();

        // Numéro réduit aux chiffres pour wa.me.
        $response->assertJsonPath('data.phone', '221771234567');
        // Message contextualisé contenant le sujet et la référence.
        $response->assertJsonPath('data.message', 'Bonjour, je vous contacte au sujet de : Réservation (réf. BK-123).');
        // URL correctement encodée.
        $this->assertStringContainsString('https://wa.me/221771234567?text=', $response->json('data.url'));
        $this->assertStringContainsString('BK-123', rawurldecode($response->json('data.url')));
    }
}
