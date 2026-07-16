<?php

namespace Tests\Feature\Transversal;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Messages de contact (F2.8.1) : dépôt public depuis la page Contact, puis
 * consultation et traitement réservés à l'équipe (permission `traiter:demandes`).
 */
class ContactMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function agent(): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);

        return $agent;
    }

    public function test_un_visiteur_anonyme_envoie_un_message_de_contact(): void
    {
        $response = $this->postJson('/api/v1/contact', [
            'name' => 'Awa Diop',
            'email' => 'awa@example.com',
            'subject' => 'Question villa',
            'message' => 'La villa de Saly est-elle disponible en août ?',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.contact_message.status', 'nouveau');

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'awa@example.com',
            'status' => ContactMessageStatus::NOUVEAU->value,
            'handled_by' => null,
        ]);
    }

    public function test_les_coordonnees_du_siege_sont_publiques(): void
    {
        $this->getJson('/api/v1/contact-info')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['contact' => ['email', 'phone', 'address', 'latitude', 'longitude']],
            ])
            // Adresse/coordonnées présentes (valeur exacte = réglage, non figée ici).
            ->assertJsonPath('data.contact.address', fn ($v) => is_string($v) && $v !== '')
            ->assertJsonPath('data.contact.latitude', fn ($v) => is_string($v) && $v !== '');
    }

    public function test_le_message_de_contact_est_valide(): void
    {
        $this->postJson('/api/v1/contact', ['name' => 'X'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'message']);
    }

    public function test_un_anonyme_ne_peut_pas_lister_les_messages(): void
    {
        $this->getJson('/api/v1/admin/contact-messages')->assertUnauthorized();
    }

    public function test_un_utilisateur_sans_permission_ne_peut_pas_lister(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/contact-messages')->assertForbidden();
    }

    public function test_un_agent_liste_les_messages(): void
    {
        ContactMessage::create([
            'name' => 'Awa', 'email' => 'awa@example.com',
            'message' => 'Bonjour', 'status' => ContactMessageStatus::NOUVEAU->value,
        ]);

        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/contact-messages')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_un_agent_marque_un_message_comme_traite(): void
    {
        $message = ContactMessage::create([
            'name' => 'Awa', 'email' => 'awa@example.com',
            'message' => 'Bonjour', 'status' => ContactMessageStatus::NOUVEAU->value,
        ]);

        $agent = $this->agent();
        Sanctum::actingAs($agent);

        $this->patchJson("/api/v1/admin/contact-messages/{$message->id}", [
            'status' => ContactMessageStatus::TRAITE->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.contact_message.status', 'traite');

        $this->assertDatabaseHas('contact_messages', [
            'id' => $message->id,
            'status' => ContactMessageStatus::TRAITE->value,
            'handled_by' => $agent->id,
        ]);
        $this->assertNotNull($message->fresh()->handled_at);
    }
}
