<?php

namespace Tests\Feature\Transversal;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\NewContactMessageNotification;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
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
        $agent->givePermissionTo(AdminPermission::operational()); // F7.1.b : agent pleinement outillé (droits désormais délégués, plus portés par le rôle)

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
            ->assertJsonPath('data.contact.latitude', fn ($v) => is_string($v) && $v !== '')
            // F7.2.l — Aucun réseau social n'est renseigné par défaut : le bloc
            // existe mais reste VIDE (le pied de page n'affiche alors rien).
            ->assertJsonPath('data.contact.social', []);
    }

    public function test_seuls_les_reseaux_sociaux_renseignes_sont_publies(): void
    {
        // F7.2.l — Les liens du pied de page viennent des réglages back-office.
        $settings = app(\App\Support\SettingsRepository::class);
        $settings->set('social.facebook', 'https://facebook.com/kaikun360');
        $settings->set('social.tiktok', 'https://tiktok.com/@kaikun360');

        $social = $this->getJson('/api/v1/contact-info')->assertOk()->json('data.contact.social');

        // Les réseaux vides sont OMIS : le frontend n'a rien à filtrer et aucun
        // lien mort ne peut apparaître dans le pied de page public.
        $this->assertSame(
            ['facebook' => 'https://facebook.com/kaikun360', 'tiktok' => 'https://tiktok.com/@kaikun360'],
            $social,
        );
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

    // =========================================================================
    // F8.15.c — ce dont l'écran back-office a besoin
    //
    // ⚠️ Ces routes existaient depuis F2.8.1 et n'avaient AUCUN appelant : la
    // page Contact — canal de conversion prioritaire du cahier des charges —
    // écrivait en base et personne ne lisait jamais. Les deux manques ci-dessous
    // sont apparus en construisant l'écran.
    // =========================================================================

    public function test_la_liste_dit_quel_agent_a_traite_le_message(): void
    {
        $message = ContactMessage::create([
            'name' => 'Awa', 'email' => 'awa@example.com',
            'message' => 'Bonjour', 'status' => ContactMessageStatus::NOUVEAU->value,
        ]);

        $agent = $this->agent();
        Sanctum::actingAs($agent);

        // Avant traitement : personne n'est responsable, rien à afficher.
        $this->getJson('/api/v1/admin/contact-messages')
            ->assertOk()
            ->assertJsonPath('data.0.handled_by', null);

        $this->patchJson("/api/v1/admin/contact-messages/{$message->id}", [
            'status' => ContactMessageStatus::TRAITE->value,
        ])->assertOk();

        // Après : le NOM de l'agent, pas son identifiant — sans ce chargement,
        // la clé était absente de la réponse (`whenLoaded`) et deux agents
        // pouvaient rappeler le même prospect.
        $this->getJson('/api/v1/admin/contact-messages')
            ->assertOk()
            ->assertJsonPath('data.0.handled_by', $agent->name);
    }

    /**
     * F8.15.c bis — l'arrivée d'un message alerte l'équipe.
     *
     * F8.15.c avait donné un écran à ce courrier, mais rien n'avertissait de son
     * arrivée : il fallait penser à ouvrir l'onglet. Le seul relais prévu était
     * le webhook n8n, **non configuré** — donc silencieux.
     */
    public function test_le_depot_d_un_message_alerte_l_equipe(): void
    {
        NotificationFacade::fake();

        $agent = $this->agent();

        $this->postJson('/api/v1/contact', [
            'name' => 'Awa Diop',
            'email' => 'awa@example.com',
            'subject' => 'Villa à Saly',
            'message' => 'Est-elle disponible en août ?',
        ])->assertCreated();

        NotificationFacade::assertSentTo($agent, NewContactMessageNotification::class);
    }

    /**
     * ⚠️ Le dépôt est **public** : un visiteur sans compte ne doit évidemment
     * pas recevoir l'alerte interne, et un utilisateur ordinaire non plus.
     */
    public function test_l_alerte_ne_part_qu_a_l_equipe(): void
    {
        NotificationFacade::fake();

        $this->agent();
        $simple = User::factory()->create();

        $this->postJson('/api/v1/contact', [
            'name' => 'Awa Diop',
            'email' => 'awa@example.com',
            'message' => 'Bonjour',
        ])->assertCreated();

        NotificationFacade::assertNotSentTo($simple, NewContactMessageNotification::class);
    }

    public function test_le_compteur_des_messages_a_traiter_ignore_le_filtre(): void
    {
        ContactMessage::create([
            'name' => 'Awa', 'email' => 'awa@example.com',
            'message' => 'En attente', 'status' => ContactMessageStatus::NOUVEAU->value,
        ]);
        ContactMessage::create([
            'name' => 'Bou', 'email' => 'bou@example.com',
            'message' => 'Déjà vu', 'status' => ContactMessageStatus::TRAITE->value,
        ]);

        Sanctum::actingAs($this->agent());

        // Sur la vue « traités », la liste ne montre que le message traité — mais
        // le compteur doit continuer de dire ce qui ATTEND, sinon l'écran ment
        // sur la charge restante dès qu'on change de filtre.
        $this->getJson('/api/v1/admin/contact-messages?status=traite')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.pending', 1);
    }
}
