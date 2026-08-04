<?php

namespace Tests\Feature\Transversal;

use App\Models\Conversation;
use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Notifications\NewMessageNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests de la messagerie de l'espace client (phase F3.7).
 *
 * Couvre les endpoints `/messages*` : liste des conversations + compteur de
 * non-lus, détail d'un fil (marquage lu), envoi d'un message (+ notification du
 * correspondant), ouverture d'une conversation (+ dédoublonnage), et surtout
 * l'ISOLATION stricte — un fil dont on n'est pas participant renvoie 404.
 */
class MessagingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Depuis F8.12, ouvrir un fil EN DÉSIGNANT son destinataire relève de la
        // permission `repondre:messages` : les rôles doivent donc exister.
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Un agent du support : c'est lui, depuis F8.12, qui a le droit d'ouvrir un
     * fil vers un compte donné (le client, lui, passe par /messages/support).
     */
    private function agentDuSupport(): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);
        $agent->givePermissionTo(AdminPermission::REPONDRE_MESSAGES->value);

        return $agent;
    }

    /**
     * Crée une conversation directe entre deux utilisateurs, avec un message
     * initial émis par `$from`.
     */
    private function conversationEntre(User $from, User $to, string $body = 'Bonjour'): Conversation
    {
        $conversation = Conversation::create();
        $conversation->participants()->attach([$from->id, $to->id]);

        $message = $conversation->messages()->create([
            'sender_id' => $from->id,
            'body' => $body,
        ]);
        $conversation->update(['last_message_at' => $message->created_at]);

        return $conversation;
    }

    public function test_la_liste_exige_d_etre_authentifie(): void
    {
        $this->getJson('/api/v1/messages')->assertStatus(401);
    }

    public function test_liste_de_mes_conversations_avec_compteur_de_non_lus(): void
    {
        $client = User::factory()->create();
        $agent = User::factory()->create();
        $this->conversationEntre($agent, $client, 'Message non lu par le client');

        Sanctum::actingAs($client);

        $this->getJson('/api/v1/messages')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id', 'subject', 'context_label', 'counterparts',
                    'last_message', 'unread_count', 'last_message_at', 'created_at',
                ]],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'unread_count',
            ])
            // Le correspondant listé est l'AUTRE participant (l'agent), pas soi.
            ->assertJsonPath('data.0.counterparts.0.id', $agent->id)
            // Un message reçu et non ouvert compte comme non lu.
            ->assertJsonPath('data.0.unread_count', 1)
            ->assertJsonPath('unread_count', 1);
    }

    public function test_le_detail_renvoie_les_messages_et_marque_comme_lu(): void
    {
        $client = User::factory()->create();
        $agent = User::factory()->create();
        $conversation = $this->conversationEntre($agent, $client, 'Bonjour, comment puis-je aider ?');

        Sanctum::actingAs($client);

        $this->getJson("/api/v1/messages/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $conversation->id)
            ->assertJsonCount(1, 'data.messages')
            ->assertJsonPath('data.messages.0.body', 'Bonjour, comment puis-je aider ?')
            ->assertJsonPath('data.messages.0.is_mine', false);

        // Après ouverture, plus aucun non-lu sur ce fil.
        $this->getJson('/api/v1/messages')
            ->assertJsonPath('data.0.unread_count', 0)
            ->assertJsonPath('unread_count', 0);
    }

    public function test_on_ne_peut_pas_ouvrir_le_fil_d_autrui(): void
    {
        $client = User::factory()->create();
        $agent = User::factory()->create();
        $intrus = User::factory()->create();
        $conversation = $this->conversationEntre($agent, $client);

        Sanctum::actingAs($intrus);

        // Fil dont l'intrus n'est pas participant → 404 (aucune fuite).
        $this->getJson("/api/v1/messages/{$conversation->id}")->assertStatus(404);
    }

    public function test_envoyer_un_message_notifie_le_correspondant(): void
    {
        Notification::fake();

        $client = User::factory()->create();
        $agent = User::factory()->create();
        $conversation = $this->conversationEntre($agent, $client);

        Sanctum::actingAs($client);

        $this->postJson("/api/v1/messages/{$conversation->id}/messages", [
            'body' => 'Merci pour votre retour !',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.message.body', 'Merci pour votre retour !')
            ->assertJsonPath('data.message.is_mine', true);

        // Le fil remonte en tête (last_message_at rafraîchi) et notifie l'AGENT,
        // jamais l'émetteur.
        Notification::assertSentTo($agent, NewMessageNotification::class);
        Notification::assertNotSentTo($client, NewMessageNotification::class);
    }

    public function test_on_ne_peut_pas_ecrire_dans_le_fil_d_autrui(): void
    {
        $client = User::factory()->create();
        $agent = User::factory()->create();
        $intrus = User::factory()->create();
        $conversation = $this->conversationEntre($agent, $client);

        Sanctum::actingAs($intrus);

        $this->postJson("/api/v1/messages/{$conversation->id}/messages", [
            'body' => 'Je ne devrais pas pouvoir écrire ici.',
        ])->assertStatus(404);
    }

    public function test_l_equipe_ouvre_une_conversation_et_notifie_le_destinataire(): void
    {
        Notification::fake();

        $client = User::factory()->create();
        $agent = $this->agentDuSupport();

        Sanctum::actingAs($agent);

        $this->postJson('/api/v1/messages', [
            'recipient_id' => $client->id,
            'subject' => 'Question sur votre réservation',
            'body' => 'Bonjour, une précision à vous demander.',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.conversation.subject', 'Question sur votre réservation')
            ->assertJsonPath('data.conversation.counterparts.0.id', $client->id);

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseHas('messages', ['body' => 'Bonjour, une précision à vous demander.']);
        Notification::assertSentTo($client, NewMessageNotification::class);
    }

    public function test_ouvrir_un_second_fil_direct_reutilise_le_meme(): void
    {
        $client = User::factory()->create();
        $agent = $this->agentDuSupport();

        Sanctum::actingAs($agent);

        $this->postJson('/api/v1/messages', ['recipient_id' => $client->id, 'body' => 'Premier'])
            ->assertStatus(201);
        $this->postJson('/api/v1/messages', ['recipient_id' => $client->id, 'body' => 'Second'])
            ->assertStatus(201);

        // Pas de doublon : un seul fil direct, deux messages.
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_on_ne_peut_pas_se_parler_a_soi_meme(): void
    {
        $agent = $this->agentDuSupport();

        Sanctum::actingAs($agent);

        $this->postJson('/api/v1/messages', [
            'recipient_id' => $agent->id,
            'body' => 'Coucou moi-même',
        ])->assertStatus(422);
    }

    public function test_un_client_ne_peut_pas_designer_son_destinataire(): void
    {
        $client = User::factory()->create();
        $proprietaire = User::factory()->create();

        Sanctum::actingAs($client);

        // ⚠️ F8.12 : cette route était ouverte à tous. Elle ne l'est plus —
        // le client passe par /messages/support, et c'est l'agent qui décide
        // d'ajouter un tiers au fil. Voir SupportMessagingTest.
        $this->postJson('/api/v1/messages', [
            'recipient_id' => $proprietaire->id,
            'body' => 'Contactons-nous directement',
        ])->assertStatus(403);
    }

    public function test_le_compteur_de_non_lus(): void
    {
        $client = User::factory()->create();
        $agent = User::factory()->create();
        $this->conversationEntre($agent, $client, 'Un message reçu');

        Sanctum::actingAs($client);

        $this->getJson('/api/v1/messages/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);
    }
}
