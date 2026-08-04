<?php

namespace Tests\Feature\Transversal;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Models\Attendance;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Immo\Models\Property;
use App\Modules\Stay\Models\Stay;
use App\Notifications\NewMessageNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests F8.12 — la messagerie « support pivot », de bout en bout.
 *
 * Ce fichier couvre ce que `MessagingTest` (F3.7) ne pouvait pas couvrir : le
 * socle savait lire un fil et y répondre, mais **rien ne savait en ouvrir un**.
 * On éprouve donc le PARCOURS, pas les couches :
 *
 *   client écrit au support → un agent lui est assigné → l'agent voit le fil
 *   dans sa boîte de réception → il répond → le client reçoit la réponse.
 *
 * Et les règles qui tiennent l'architecture :
 *   - le client ne DÉSIGNE jamais son interlocuteur (`POST /messages` est
 *     réservé à l'équipe depuis cette tranche) ;
 *   - réécrire à propos du même dossier reprend le fil au lieu d'en empiler un ;
 *   - un dossier qui n'est pas le sien ne peut pas être cité ;
 *   - un fil sans agent de permanence n'est jamais perdu.
 */
class SupportMessagingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Un agent de permanence. ⚠️ Depuis F8.12.b, **le rôle suffit** :
     * `repondre:messages` est portée par `agent_kaikun`, aucun droit à déléguer
     * — tout agent est de permanence d'office.
     */
    private function agentDePermanence(string $name = 'Awa Diop'): User
    {
        $agent = User::factory()->create(['name' => $name]);
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);

        return $agent;
    }

    /**
     * Fait pointer l'entrée d'un agent (pointeuse F7.1.c) : il devient « en
     * poste », donc prioritaire dans l'assignation.
     */
    private function pointeSonEntree(User $agent): void
    {
        Attendance::create(['user_id' => $agent->id, 'started_at' => now()]);
    }

    private function client(): User
    {
        $client = User::factory()->create();
        $client->assignRole(UserRole::CLIENT->value);

        return $client;
    }

    public function test_le_client_ouvre_un_fil_avec_le_support_et_un_agent_lui_est_assigne(): void
    {
        Notification::fake();

        $agent = $this->agentDePermanence();
        $client = $this->client();

        Sanctum::actingAs($client);

        $this->postJson('/api/v1/messages/support', [
            'body' => 'Bonjour, la caution est-elle restituée sous combien de jours ?',
        ])
            ->assertStatus(201)
            // L'interlocuteur est NOMMÉ : c'est l'arbitrage produit de F8.11
            // (« le contact humain fait la confiance »), repris ici.
            ->assertJsonPath('data.conversation.assigned_agent.name', 'Awa Diop')
            ->assertJsonPath('data.conversation.is_closed', false);

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseHas('conversations', ['assigned_agent_id' => $agent->id]);

        // L'agent est prévenu ; le client ne s'auto-notifie pas.
        Notification::assertSentTo($agent, NewMessageNotification::class);
        Notification::assertNotSentTo($client, NewMessageNotification::class);
    }

    public function test_le_fil_le_moins_charge_est_assigne_a_l_agent_le_moins_charge(): void
    {
        $premier = $this->agentDePermanence('Awa Diop');
        $second = $this->agentDePermanence('Moussa Fall');

        // Deux clients écrivent : le second doit tomber sur l'autre agent.
        Sanctum::actingAs($this->client());
        $this->postJson('/api/v1/messages/support', ['body' => 'Premier message'])->assertStatus(201);

        Sanctum::actingAs($this->client());
        $this->postJson('/api/v1/messages/support', ['body' => 'Second message'])
            ->assertStatus(201)
            ->assertJsonPath('data.conversation.assigned_agent.id', $second->id);

        $this->assertSame(1, $premier->assignedConversations()->count());
        $this->assertSame(1, $second->assignedConversations()->count());
    }

    public function test_sans_agent_de_permanence_le_message_n_est_jamais_perdu(): void
    {
        $client = $this->client();
        Sanctum::actingAs($client);

        // Personne ne porte `repondre:messages` : le fil part quand même, non
        // assigné — il apparaîtra dans « Non assignés » au back-office.
        $this->postJson('/api/v1/messages/support', ['body' => 'Y a-t-il quelqu’un ?'])
            ->assertStatus(201)
            ->assertJsonPath('data.conversation.assigned_agent', null);

        $this->assertDatabaseHas('conversations', ['assigned_agent_id' => null]);
        $this->assertDatabaseHas('messages', ['body' => 'Y a-t-il quelqu’un ?']);
    }

    public function test_le_dossier_cite_est_rattache_au_fil(): void
    {
        $this->agentDePermanence();
        $client = $this->client();
        $demande = ServiceRequest::factory()->create(['user_id' => $client->id]);

        Sanctum::actingAs($client);

        $this->postJson('/api/v1/messages/support', [
            'body' => 'Où en est ma demande ?',
            'context_type' => 'demande',
            'context_id' => $demande->id,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.conversation.context_label', 'Demande');

        $this->assertDatabaseHas('conversations', [
            'context_type' => ServiceRequest::class,
            'context_id' => $demande->id,
        ]);
    }

    public function test_le_dossier_d_autrui_ne_peut_pas_etre_cite(): void
    {
        $this->agentDePermanence();
        $client = $this->client();
        $demandeDAutrui = ServiceRequest::factory()->create(['user_id' => $this->client()->id]);

        Sanctum::actingAs($client);

        // Le message part (on ne laisse jamais le client sans recours), mais le
        // contexte est IGNORÉ : sinon on offrirait un moyen de sonder les
        // dossiers d'autrui.
        $this->postJson('/api/v1/messages/support', [
            'body' => 'Et cette demande ?',
            'context_type' => 'demande',
            'context_id' => $demandeDAutrui->id,
        ])->assertStatus(201);

        $this->assertDatabaseHas('conversations', ['context_type' => null, 'context_id' => null]);
    }

    public function test_une_fiche_du_catalogue_peut_etre_citee_par_n_importe_qui(): void
    {
        $this->agentDePermanence();
        $stay = Stay::factory()->create();

        Sanctum::actingAs($this->client());

        // Le catalogue est public : c'est justement le visiteur intéressé, qui
        // n'a encore rien réservé, qui pose la question.
        $this->postJson('/api/v1/messages/support', [
            'body' => 'Ce logement accepte-t-il les animaux ?',
            'context_type' => 'nuitee',
            'context_id' => $stay->id,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.conversation.context_label', 'Nuitée');
    }

    public function test_reecrire_sur_le_meme_dossier_reprend_le_fil(): void
    {
        $this->agentDePermanence();
        $client = $this->client();
        // Pas de factory pour Booking dans ce projet : on la construit à la main
        // (même geste que les tests de dossiers back-office).
        $stay = Stay::factory()->create();
        $reservation = Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => $client->id,
            'bookable_type' => Stay::class,
            'bookable_id' => $stay->id,
            'start_date' => today(),
            'end_date' => today()->addDays(2),
            'guests' => 2,
            'amount_xof' => 60_000,
            'commission_xof' => 6_000,
            'status' => 'en_attente',
        ]);

        Sanctum::actingAs($client);

        foreach (['Première question', 'Seconde question'] as $texte) {
            $this->postJson('/api/v1/messages/support', [
                'body' => $texte,
                'context_type' => 'reservation',
                'context_id' => $reservation->id,
            ])->assertStatus(201);
        }

        // Un seul fil, deux messages : pour le client comme pour l'agent, c'est
        // la même conversation.
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_le_client_ne_peut_pas_choisir_son_interlocuteur(): void
    {
        $proprietaire = User::factory()->create();
        $client = $this->client();

        Sanctum::actingAs($client);

        // ⚠️ Règle d'architecture : le fil naît TOUJOURS chez Kaikun. Écrire
        // directement au propriétaire sortirait l'échange de toute supervision.
        $this->postJson('/api/v1/messages', [
            'recipient_id' => $proprietaire->id,
            'body' => 'Appelez-moi directement',
        ])->assertStatus(403);
    }

    public function test_l_equipe_voit_le_fil_dans_sa_boite_de_reception_et_repond(): void
    {
        Notification::fake();

        $agent = $this->agentDePermanence();
        $client = $this->client();

        Sanctum::actingAs($client);
        $this->postJson('/api/v1/messages/support', ['body' => 'Bonjour, une question.'])
            ->assertStatus(201);

        $conversation = Conversation::firstOrFail();

        Sanctum::actingAs($agent);

        // La file par défaut = MES fils ouverts, et celui-ci attend une réponse.
        $this->getJson('/api/v1/admin/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.awaiting_reply', true)
            ->assertJsonPath('data.0.is_mine', true)
            ->assertJsonPath('data.0.requester.id', $client->id);

        $this->postJson("/api/v1/admin/conversations/{$conversation->id}/messages", [
            'body' => 'Bonjour, je regarde votre dossier tout de suite.',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.conversation.awaiting_reply', false);

        // Le client reçoit la réponse dans SON fil.
        Notification::assertSentTo($client, NewMessageNotification::class);

        Sanctum::actingAs($client);
        $this->getJson("/api/v1/messages/{$conversation->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.messages')
            ->assertJsonPath('data.messages.1.body', 'Bonjour, je regarde votre dossier tout de suite.');
    }

    public function test_repondre_a_un_fil_non_assigne_le_prend_en_charge(): void
    {
        $client = $this->client();
        Sanctum::actingAs($client);
        // Aucun agent de permanence au moment du dépôt.
        $this->postJson('/api/v1/messages/support', ['body' => 'Personne ?'])->assertStatus(201);

        $conversation = Conversation::firstOrFail();
        $this->assertNull($conversation->assigned_agent_id);

        $agent = $this->agentDePermanence();
        Sanctum::actingAs($agent);

        // Il apparaît dans « Non assignés »…
        $this->getJson('/api/v1/admin/conversations?scope=unassigned')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // …et répondre, c'est le prendre.
        $this->postJson("/api/v1/admin/conversations/{$conversation->id}/messages", [
            'body' => 'Me voilà, je m’en occupe.',
        ])->assertStatus(201);

        $this->assertSame($agent->id, $conversation->fresh()->assigned_agent_id);
    }

    public function test_clore_un_fil_le_sort_de_la_file_et_le_client_le_rouvre_en_ecrivant(): void
    {
        $agent = $this->agentDePermanence();
        $client = $this->client();

        Sanctum::actingAs($client);
        $this->postJson('/api/v1/messages/support', ['body' => 'Question réglée depuis'])
            ->assertStatus(201);
        $conversation = Conversation::firstOrFail();

        Sanctum::actingAs($agent);
        $this->patchJson("/api/v1/admin/conversations/{$conversation->id}", ['closed' => true])
            ->assertOk()
            ->assertJsonPath('data.conversation.is_closed', true);

        // Hors de la file par défaut, présent dans l'archive.
        $this->getJson('/api/v1/admin/conversations')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/admin/conversations?closed=1')->assertJsonCount(1, 'data');

        // Le client relance : le fil revient dans la file, sinon personne ne
        // verrait jamais sa relance.
        Sanctum::actingAs($client);
        $this->postJson("/api/v1/messages/{$conversation->id}/messages", ['body' => 'Finalement, autre chose…'])
            ->assertStatus(201);

        Sanctum::actingAs($agent);
        $this->getJson('/api/v1/admin/conversations')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.awaiting_reply', true);
    }

    public function test_reassigner_exige_un_agent_du_vivier(): void
    {
        $agent = $this->agentDePermanence();
        $collegue = $this->agentDePermanence('Fatou Sow');
        $simpleClient = $this->client();

        Sanctum::actingAs($this->client());
        $this->postJson('/api/v1/messages/support', ['body' => 'Bonjour'])->assertStatus(201);
        $conversation = Conversation::firstOrFail();

        Sanctum::actingAs($agent);

        // Un compte hors vivier ne peut pas recevoir le dossier : il n'aurait
        // pas le droit d'y répondre, le fil deviendrait muet.
        $this->patchJson("/api/v1/admin/conversations/{$conversation->id}", [
            'assigned_agent_id' => $simpleClient->id,
        ])->assertStatus(422);

        $this->patchJson("/api/v1/admin/conversations/{$conversation->id}", [
            'assigned_agent_id' => $collegue->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.conversation.assigned_agent.id', $collegue->id);
    }

    public function test_la_releve_ne_renvoie_que_les_messages_nouveaux(): void
    {
        $agent = $this->agentDePermanence();
        $client = $this->client();

        Sanctum::actingAs($client);
        $this->postJson('/api/v1/messages/support', ['body' => 'Premier message'])->assertStatus(201);
        $conversation = Conversation::firstOrFail();

        // Le fil est ouvert à l'écran : premier chargement complet.
        $premier = $this->getJson("/api/v1/messages/{$conversation->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.messages');
        $dernierId = $premier->json('data.messages.0.id');

        // Battement à vide : rien de neuf, donc rien à afficher.
        $this->getJson("/api/v1/messages/{$conversation->id}?after={$dernierId}")
            ->assertOk()
            ->assertJsonCount(0, 'data.messages');

        // L'agent répond pendant que le client garde son écran ouvert.
        Sanctum::actingAs($agent);
        $this->postJson("/api/v1/admin/conversations/{$conversation->id}/messages", [
            'body' => 'Bonjour, je regarde.',
        ])->assertStatus(201);

        // Battement suivant : SEULE la réponse remonte — l'écran l'ajoute à la
        // suite sans retélécharger l'historique.
        Sanctum::actingAs($client);
        $this->getJson("/api/v1/messages/{$conversation->id}?after={$dernierId}")
            ->assertOk()
            ->assertJsonCount(1, 'data.messages')
            ->assertJsonPath('data.messages.0.body', 'Bonjour, je regarde.');
    }

    public function test_une_releve_a_vide_ne_touche_pas_au_marquage_de_lecture(): void
    {
        $agent = $this->agentDePermanence();
        $client = $this->client();

        Sanctum::actingAs($client);
        $this->postJson('/api/v1/messages/support', ['body' => 'Bonjour'])->assertStatus(201);
        $conversation = Conversation::firstOrFail();

        $lu = $client->conversations()->find($conversation->id)->pivot->last_read_at;

        // Un battement sans nouveauté ne doit produire AUCUNE écriture : sinon
        // chaque fil ouvert écrirait en base toutes les dix secondes pour rien.
        $this->travel(1)->minute();
        $this->getJson("/api/v1/messages/{$conversation->id}?after=999999")->assertOk();

        $this->assertEquals(
            $lu,
            $client->conversations()->find($conversation->id)->pivot->last_read_at,
        );
    }

    public function test_la_releve_du_back_office_ne_renvoie_que_les_messages_nouveaux(): void
    {
        $agent = $this->agentDePermanence();
        $client = $this->client();

        Sanctum::actingAs($client);
        $this->postJson('/api/v1/messages/support', ['body' => 'Première question'])->assertStatus(201);
        $conversation = Conversation::firstOrFail();

        Sanctum::actingAs($agent);
        $premier = $this->getJson("/api/v1/admin/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.conversation.messages');
        $dernierId = $premier->json('data.conversation.messages.0.id');

        // Le client relance pendant que l'agent a la fiche sous les yeux.
        Sanctum::actingAs($client);
        $this->postJson("/api/v1/messages/{$conversation->id}/messages", ['body' => 'Toujours là ?'])
            ->assertStatus(201);

        Sanctum::actingAs($agent);
        $this->getJson("/api/v1/admin/conversations/{$conversation->id}?after={$dernierId}")
            ->assertOk()
            ->assertJsonCount(1, 'data.conversation.messages')
            ->assertJsonPath('data.conversation.messages.0.body', 'Toujours là ?');
    }

    public function test_tout_agent_accede_a_la_boite_de_reception_sans_delegation(): void
    {
        $nouvelAgent = User::factory()->create();
        $nouvelAgent->assignRole(UserRole::AGENT_KAIKUN->value);

        Sanctum::actingAs($nouvelAgent);

        // ⚠️ **Exception assumée au grant pur de F7.1.b** (arbitrage produit
        // F8.12.b) : répondre aux clients est le métier de base d'un agent, pas
        // un levier sensible. Un droit qu'il faudrait penser à cocher laisserait
        // les fils s'entasser dans « Non assignés » à chaque arrivée.
        $this->getJson('/api/v1/admin/conversations')->assertOk();
        $this->assertTrue($nouvelAgent->can(AdminPermission::REPONDRE_MESSAGES->value));

        // Les leviers, eux, restent bel et bien cloisonnés.
        $this->getJson('/api/v1/admin/payments')->assertStatus(403);
    }

    public function test_le_fil_va_a_un_agent_EN_POSTE_meme_s_il_est_plus_charge(): void
    {
        $absent = $this->agentDePermanence('Awa Diop');
        $enPoste = $this->agentDePermanence('Moussa Fall');
        $this->pointeSonEntree($enPoste);

        // On charge celui qui est en poste : il doit malgré tout recevoir le
        // fil. Un agent parti à 18 h ne verrait le message qu'au matin — le
        // client aurait attendu la nuit pour rien.
        Sanctum::actingAs($this->client());
        $this->postJson('/api/v1/messages/support', ['body' => 'Première question'])
            ->assertStatus(201)
            ->assertJsonPath('data.conversation.assigned_agent.id', $enPoste->id);

        Sanctum::actingAs($this->client());
        $this->postJson('/api/v1/messages/support', ['body' => 'Deuxième question'])
            ->assertStatus(201)
            ->assertJsonPath('data.conversation.assigned_agent.id', $enPoste->id);

        $this->assertSame(0, $absent->assignedConversations()->count());
    }

    public function test_si_personne_n_est_en_poste_le_fil_va_au_moins_charge_de_l_equipe(): void
    {
        $premier = $this->agentDePermanence('Awa Diop');
        $this->agentDePermanence('Moussa Fall');

        // Nuit, week-end : aucun pointage ouvert. Le message ne doit pas
        // attendre pour autant — repli sur tout le vivier.
        Sanctum::actingAs($this->client());
        $this->postJson('/api/v1/messages/support', ['body' => 'Il est 23 h et j’ai un souci.'])
            ->assertStatus(201)
            ->assertJsonPath('data.conversation.assigned_agent.id', $premier->id);
    }

    public function test_l_agent_en_poste_le_moins_charge_passe_devant_son_collegue_en_poste(): void
    {
        $charge = $this->agentDePermanence('Awa Diop');
        $libre = $this->agentDePermanence('Moussa Fall');
        $this->pointeSonEntree($charge);
        $this->pointeSonEntree($libre);

        // Deux agents présents : le premier fil part au plus ancien, le second
        // à celui qui n'a encore rien — « libre » veut dire zéro conversation
        // en cours, pas « inscrit sur la liste ».
        Sanctum::actingAs($this->client());
        $this->postJson('/api/v1/messages/support', ['body' => 'Bonjour'])
            ->assertStatus(201)
            ->assertJsonPath('data.conversation.assigned_agent.id', $charge->id);

        Sanctum::actingAs($this->client());
        $this->postJson('/api/v1/messages/support', ['body' => 'Bonjour aussi'])
            ->assertStatus(201)
            ->assertJsonPath('data.conversation.assigned_agent.id', $libre->id);
    }

    // --- F8.12.c — faire entrer un tiers, et masquer les coordonnées ----------

    /**
     * Un propriétaire, et un bien qui lui appartient : le décor minimal pour
     * éprouver l'ajout d'un tiers « rattaché au dossier ».
     */
    private function proprietaireAvecBien(): array
    {
        $proprietaire = User::factory()->create(['name' => 'Ousmane Ba']);
        $proprietaire->assignRole(UserRole::PROPRIETAIRE->value);
        $bien = Property::factory()->create(['owner_id' => $proprietaire->id]);

        return [$proprietaire, $bien];
    }

    public function test_la_personne_du_dossier_est_proposee_a_l_agent(): void
    {
        $agent = $this->agentDePermanence();
        $client = $this->client();
        [$proprietaire, $bien] = $this->proprietaireAvecBien();

        Sanctum::actingAs($client);
        $this->postJson('/api/v1/messages/support', [
            'body' => 'Ce bien est-il encore disponible ?',
            'context_type' => 'bien',
            'context_id' => $bien->id,
        ])->assertStatus(201);
        $conversation = Conversation::firstOrFail();

        Sanctum::actingAs($agent);

        // Proposée en un clic, avec la raison : c'est le cas courant.
        $this->getJson("/api/v1/admin/conversations/{$conversation->id}/candidates")
            ->assertOk()
            ->assertJsonPath('data.dossier.id', $proprietaire->id)
            ->assertJsonPath('data.dossier.from_context', 'Bien immobilier');
    }

    public function test_l_agent_fait_entrer_le_tiers_qui_voit_le_fil_et_y_ecrit(): void
    {
        Notification::fake();

        $agent = $this->agentDePermanence();
        $client = $this->client();
        [$proprietaire, $bien] = $this->proprietaireAvecBien();

        Sanctum::actingAs($client);
        $this->postJson('/api/v1/messages/support', [
            'body' => 'Le logement accepte-t-il les animaux ?',
            'context_type' => 'bien',
            'context_id' => $bien->id,
        ])->assertStatus(201);
        $conversation = Conversation::firstOrFail();

        Sanctum::actingAs($agent);
        $this->postJson("/api/v1/admin/conversations/{$conversation->id}/participants", [
            'user_id' => $proprietaire->id,
        ])->assertOk();

        // Le tiers est prévenu, sinon il ne saurait pas qu'on l'attend.
        Notification::assertSentTo($proprietaire, NewMessageNotification::class);

        // Il voit TOUT l'historique — sans quoi il répondrait à une question
        // qu'il n'a pas lue — et peut écrire dans le fil.
        Sanctum::actingAs($proprietaire);
        $this->getJson("/api/v1/messages/{$conversation->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.messages');

        $this->postJson("/api/v1/messages/{$conversation->id}/messages", [
            'body' => 'Oui, les animaux sont acceptés.',
        ])->assertStatus(201);

        // Et le client le voit répondre, nommé par son rôle.
        Sanctum::actingAs($client);
        $this->getJson("/api/v1/messages/{$conversation->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.messages')
            ->assertJsonFragment(['name' => 'Ousmane Ba', 'role' => 'Propriétaire', 'is_team' => false]);
    }

    public function test_un_client_ne_peut_pas_etre_ajoute_a_la_conversation_d_un_autre(): void
    {
        $agent = $this->agentDePermanence();
        $client = $this->client();
        $autreClient = $this->client();

        Sanctum::actingAs($client);
        $this->postJson('/api/v1/messages/support', ['body' => 'Bonjour'])->assertStatus(201);
        $conversation = Conversation::firstOrFail();

        Sanctum::actingAs($agent);

        // Seuls un propriétaire ou un prestataire entrent par ce geste.
        $this->postJson("/api/v1/admin/conversations/{$conversation->id}/participants", [
            'user_id' => $autreClient->id,
        ])->assertStatus(422);
    }

    public function test_le_demandeur_ne_peut_pas_etre_sorti_de_sa_propre_conversation(): void
    {
        $agent = $this->agentDePermanence();
        $client = $this->client();

        Sanctum::actingAs($client);
        $this->postJson('/api/v1/messages/support', ['body' => 'Bonjour'])->assertStatus(201);
        $conversation = Conversation::firstOrFail();

        Sanctum::actingAs($agent);

        // Le sortir laisserait un fil que son propre auteur ne pourrait plus lire.
        $this->deleteJson("/api/v1/admin/conversations/{$conversation->id}/participants/{$client->id}")
            ->assertStatus(422);

        // L'agent responsable non plus.
        $this->deleteJson("/api/v1/admin/conversations/{$conversation->id}/participants/{$agent->id}")
            ->assertStatus(422);
    }

    public function test_le_tiers_sorti_du_fil_ne_le_lit_plus_mais_ses_messages_restent(): void
    {
        $agent = $this->agentDePermanence();
        $client = $this->client();
        [$proprietaire, $bien] = $this->proprietaireAvecBien();

        Sanctum::actingAs($client);
        $this->postJson('/api/v1/messages/support', [
            'body' => 'Question sur ce bien',
            'context_type' => 'bien',
            'context_id' => $bien->id,
        ])->assertStatus(201);
        $conversation = Conversation::firstOrFail();

        Sanctum::actingAs($agent);
        $this->postJson("/api/v1/admin/conversations/{$conversation->id}/participants", [
            'user_id' => $proprietaire->id,
        ])->assertOk();

        Sanctum::actingAs($proprietaire);
        $this->postJson("/api/v1/messages/{$conversation->id}/messages", ['body' => 'Bonjour, oui.'])
            ->assertStatus(201);

        Sanctum::actingAs($agent);
        $this->deleteJson("/api/v1/admin/conversations/{$conversation->id}/participants/{$proprietaire->id}")
            ->assertOk();

        // Il ne lit plus la suite…
        Sanctum::actingAs($proprietaire);
        $this->getJson("/api/v1/messages/{$conversation->id}")->assertStatus(404);

        // …mais on ne réécrit pas l'histoire : son message reste dans le fil.
        Sanctum::actingAs($client);
        $this->getJson("/api/v1/messages/{$conversation->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.messages');
    }

    public function test_les_coordonnees_sont_masquees_entre_non_staff_et_lisibles_par_l_equipe(): void
    {
        $agent = $this->agentDePermanence();
        $client = $this->client();
        [$proprietaire, $bien] = $this->proprietaireAvecBien();

        Sanctum::actingAs($client);
        $this->postJson('/api/v1/messages/support', [
            'body' => 'Bonjour',
            'context_type' => 'bien',
            'context_id' => $bien->id,
        ])->assertStatus(201);
        $conversation = Conversation::firstOrFail();

        Sanctum::actingAs($agent);
        $this->postJson("/api/v1/admin/conversations/{$conversation->id}/participants", [
            'user_id' => $proprietaire->id,
        ])->assertOk();

        // Le tiers tente de sortir de la plateforme dès le premier message.
        Sanctum::actingAs($proprietaire);
        $this->postJson("/api/v1/messages/{$conversation->id}/messages", [
            'body' => 'Appelez-moi au 77 123 45 67 ou écrivez à ousmane@example.com, on s’arrangera.',
        ])->assertStatus(201);

        // Le client voit le message, pas les coordonnées. Le prix, lui, survit :
        // hacher « 250 000 » rendrait les messages illisibles.
        Sanctum::actingAs($client);
        $corps = $this->getJson("/api/v1/messages/{$conversation->id}")
            ->assertOk()
            ->json('data.messages.1.body');

        $this->assertStringNotContainsString('77 123 45 67', $corps);
        $this->assertStringNotContainsString('ousmane@example.com', $corps);
        $this->assertStringContainsString('Appelez-moi au', $corps);

        // ⚠️ L'ÉQUIPE voit le texte entier : sans cela, impossible de comprendre
        // un litige ni de sanctionner une désintermédiation manifeste.
        Sanctum::actingAs($agent);
        $this->getJson("/api/v1/admin/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath(
                'data.conversation.messages.1.body',
                'Appelez-moi au 77 123 45 67 ou écrivez à ousmane@example.com, on s’arrangera.',
            );
    }

    public function test_un_montant_n_est_pas_pris_pour_un_numero(): void
    {
        $agent = $this->agentDePermanence();
        $client = $this->client();

        Sanctum::actingAs($client);
        $this->postJson('/api/v1/messages/support', ['body' => 'Bonjour'])->assertStatus(201);
        $conversation = Conversation::firstOrFail();

        Sanctum::actingAs($agent);
        $this->postJson("/api/v1/admin/conversations/{$conversation->id}/messages", [
            'body' => 'Le séjour revient à 250 000 FCFA pour 3 nuits.',
        ])->assertStatus(201);

        // Un message haché serait pire que le mal qu'on soigne.
        Sanctum::actingAs($client);
        $this->getJson("/api/v1/messages/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.messages.1.body', 'Le séjour revient à 250 000 FCFA pour 3 nuits.');
    }

    public function test_le_demandeur_reste_le_client_apres_l_arrivee_d_un_tiers(): void
    {
        $agent = $this->agentDePermanence();
        $client = $this->client();
        [$proprietaire, $bien] = $this->proprietaireAvecBien();

        Sanctum::actingAs($client);
        $this->postJson('/api/v1/messages/support', [
            'body' => 'Question sur ce bien',
            'context_type' => 'bien',
            'context_id' => $bien->id,
        ])->assertStatus(201);
        $conversation = Conversation::firstOrFail();

        Sanctum::actingAs($agent);
        $this->postJson("/api/v1/admin/conversations/{$conversation->id}/participants", [
            'user_id' => $proprietaire->id,
        ])->assertOk();

        // ⚠️ Défaut trouvé en éprouvant le geste sur des données réelles : le
        // « demandeur » était déduit du premier participant NON-STAFF, donc le
        // propriétaire pouvait prendre la place du client dans la fiche — nom et
        // coordonnées compris. Il se lit désormais sur l'auteur du PREMIER
        // message, qui ne change jamais.
        $this->getJson("/api/v1/admin/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.conversation.requester.id', $client->id);

        $this->getJson('/api/v1/admin/conversations?scope=all')
            ->assertOk()
            ->assertJsonPath('data.0.requester.id', $client->id);
    }

    public function test_la_personne_du_dossier_est_ajoutable_meme_sans_role_declare(): void
    {
        $agent = $this->agentDePermanence();
        $client = $this->client();

        // Compte sans rôle Spatie (donnée importée) mais propriétaire du bien :
        // cas réel rencontré en base. C'est le DOSSIER qui fait la légitimité.
        $proprietaireSansRole = User::factory()->create(['name' => 'Pierre Robert']);
        $bien = Property::factory()->create(['owner_id' => $proprietaireSansRole->id]);

        Sanctum::actingAs($client);
        $this->postJson('/api/v1/messages/support', [
            'body' => 'Question sur ce bien',
            'context_type' => 'bien',
            'context_id' => $bien->id,
        ])->assertStatus(201);
        $conversation = Conversation::firstOrFail();

        Sanctum::actingAs($agent);
        $this->postJson("/api/v1/admin/conversations/{$conversation->id}/participants", [
            'user_id' => $proprietaireSansRole->id,
        ])->assertOk();

        // La porte reste étroite : un autre compte sans rôle est refusé.
        $inconnu = User::factory()->create();
        $this->postJson("/api/v1/admin/conversations/{$conversation->id}/participants", [
            'user_id' => $inconnu->id,
        ])->assertStatus(422);
    }

    public function test_un_client_n_atteint_pas_la_boite_de_reception(): void
    {
        Sanctum::actingAs($this->client());

        $this->getJson('/api/v1/admin/conversations')->assertStatus(403);
    }
}
