<?php

namespace Tests\Feature\Transversal;

use App\Models\Conversation;
use App\Models\User;
use App\Notifications\NewMessageNotification;
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

    public function test_ouvrir_une_conversation_cree_le_fil_et_notifie_le_destinataire(): void
    {
        Notification::fake();

        $client = User::factory()->create();
        $agent = User::factory()->create();

        Sanctum::actingAs($client);

        $this->postJson('/api/v1/messages', [
            'recipient_id' => $agent->id,
            'subject' => 'Question sur ma réservation',
            'body' => 'Bonjour, une question rapide.',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.conversation.subject', 'Question sur ma réservation')
            ->assertJsonPath('data.conversation.counterparts.0.id', $agent->id);

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseHas('messages', ['body' => 'Bonjour, une question rapide.']);
        Notification::assertSentTo($agent, NewMessageNotification::class);
    }

    public function test_ouvrir_un_second_fil_direct_reutilise_le_meme(): void
    {
        $client = User::factory()->create();
        $agent = User::factory()->create();

        Sanctum::actingAs($client);

        $this->postJson('/api/v1/messages', ['recipient_id' => $agent->id, 'body' => 'Premier'])
            ->assertStatus(201);
        $this->postJson('/api/v1/messages', ['recipient_id' => $agent->id, 'body' => 'Second'])
            ->assertStatus(201);

        // Pas de doublon : un seul fil direct, deux messages.
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_on_ne_peut_pas_se_parler_a_soi_meme(): void
    {
        $client = User::factory()->create();

        Sanctum::actingAs($client);

        $this->postJson('/api/v1/messages', [
            'recipient_id' => $client->id,
            'body' => 'Coucou moi-même',
        ])->assertStatus(422);
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
