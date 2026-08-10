<?php

namespace Tests\Feature\Transversal;

use App\Enums\BookingStatus;
use App\Enums\RequestStatus;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Mobility\Models\Vehicle;
use App\Notifications\RequestStatusChangedNotification;
use Database\Seeders\CommunesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SenegalGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Corbeille de l'espace CLIENT (F11.5) — le masquage personnel.
 *
 * Ce que ces tests protègent, dans l'ordre d'importance :
 *   1. **rien n'est jamais supprimé** — la ligne reste en base, entière, et
 *      reste visible de Kaikun. C'est LA raison d'être de la tranche : un
 *      `SoftDeletes` ici aurait laissé un client effacer une pièce de dossier
 *      dont dépendent la comptabilité, le reversement au partenaire et le
 *      règlement d'un litige ;
 *   2. on ne range que ce qui est **terminé** — un dossier vivant reste sous les
 *      yeux de la seule personne qui doit s'en occuper ;
 *   3. le masque n'agit que sur la liste du **client**, jamais ailleurs ;
 *   4. le cloisonnement — on ne range ni ne restaure le dossier d'un autre.
 */
class ClientTrashTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, SenegalGeographySeeder::class, CommunesSeeder::class]);
    }

    private function client(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::CLIENT->value);

        return $user;
    }

    /** Un agent Kaikun, pour tenir l'autre bout d'un fil de discussion. */
    private function agent(): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);
        $agent->givePermissionTo(AdminPermission::operational());

        return $agent;
    }

    /**
     * Une notification réelle du produit (et non une classe de test maison) :
     * son `data` porte les clés `title`/`body` que la corbeille lit pour
     * fabriquer son intitulé.
     */
    private function notifier(User $client): void
    {
        $client->notify(new RequestStatusChangedNotification(
            ServiceRequest::factory()->create(['user_id' => $client->id]),
        ));
    }

    /** Une réservation de véhicule au statut voulu, pour un client donné. */
    private function reservation(User $client, BookingStatus $statut): Booking
    {
        return Booking::create([
            'reference' => 'BK-'.strtoupper(uniqid()),
            'user_id' => $client->id,
            'bookable_type' => Vehicle::class,
            'bookable_id' => Vehicle::factory()->create()->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->subMonth()->addDays(2)->toDateString(),
            'guests' => 1,
            'amount_xof' => 100_000,
            'status' => $statut->value,
        ]);
    }

    // =========================================================================
    // 1. Le geste : ranger sans rien détruire
    // =========================================================================

    public function test_une_demande_cloturee_quitte_ma_liste_et_apparait_dans_ma_corbeille(): void
    {
        $client = $this->client();
        $demande = ServiceRequest::factory()->create([
            'user_id' => $client->id,
            'status' => RequestStatus::CLOTURE->value,
        ]);

        Sanctum::actingAs($client);

        $this->postJson("/api/v1/requests/{$demande->id}/hide")->assertOk();

        // Elle a quitté la liste du client…
        $this->getJson('/api/v1/requests/my')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // …et se retrouve dans la corbeille, sans compte à rebours.
        $this->getJson('/api/v1/me/trash')
            ->assertOk()
            ->assertJsonPath('data.items.0.type', 'request')
            // ⚠️ Rendu en CHAÎNE : la corbeille mélange des identifiants entiers
            // (annonces, dossiers) et un UUID (notification).
            ->assertJsonPath('data.items.0.id', (string) $demande->id)
            ->assertJsonPath('data.items.0.kind', 'record')
            ->assertJsonPath('data.items.0.days_left', null);
    }

    /**
     * ⚠️ **Le test le plus important du fichier.** Toute la tranche tient sur ce
     * point : la ligne n'est pas supprimée, même pas « en douceur ». Si un jour
     * quelqu'un remplace `hidden_at` par `SoftDeletes` pour « faire comme les
     * annonces », c'est ici que ça casse.
     */
    public function test_ranger_ne_supprime_rien_la_ligne_reste_entiere_en_base(): void
    {
        $client = $this->client();
        $reservation = $this->reservation($client, BookingStatus::TERMINEE);

        Sanctum::actingAs($client);
        $this->postJson("/api/v1/bookings/{$reservation->id}/hide")->assertOk();

        // La ligne est là, avec son montant et son statut — rien n'a bougé.
        $this->assertDatabaseHas('bookings', [
            'id' => $reservation->id,
            'reference' => $reservation->reference,
            'amount_xof' => 100_000,
            'status' => BookingStatus::TERMINEE->value,
        ]);

        // Et surtout : elle reste lisible hors de la liste du client. La fiche
        // de détail (même endpoint que le back-office consulte) répond toujours.
        $this->getJson("/api/v1/bookings/{$reservation->id}")
            ->assertOk()
            ->assertJsonPath('data.booking.reference', $reservation->reference);
    }

    public function test_une_reservation_annulee_est_rangeable(): void
    {
        $client = $this->client();
        $reservation = $this->reservation($client, BookingStatus::ANNULEE_CLIENT);

        Sanctum::actingAs($client);
        $this->postJson("/api/v1/bookings/{$reservation->id}/hide")->assertOk();

        $this->getJson('/api/v1/bookings/my')->assertOk()->assertJsonCount(0, 'data');
    }

    // =========================================================================
    // 2. On ne range que ce qui est terminé
    // =========================================================================

    public function test_une_demande_en_cours_de_traitement_ne_se_range_pas(): void
    {
        $client = $this->client();
        $demande = ServiceRequest::factory()->create([
            'user_id' => $client->id,
            'status' => RequestStatus::NEGOCIATION->value,
        ]);

        Sanctum::actingAs($client);

        // 422 AVEC LE MOTIF : « impossible » tout court laisserait sans issue —
        // le message dit ce qui bloque ET ce qu'il faut attendre.
        $this->postJson("/api/v1/requests/{$demande->id}/hide")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cette demande est encore en cours de traitement. Vous pourrez la ranger une fois qu’elle sera clôturée.']);

        $this->getJson('/api/v1/requests/my')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_une_reservation_a_venir_ne_se_range_pas_elle_s_annule(): void
    {
        $client = $this->client();
        $reservation = $this->reservation($client, BookingStatus::CONFIRMEE);

        Sanctum::actingAs($client);
        $this->postJson("/api/v1/bookings/{$reservation->id}/hide")->assertStatus(422);

        $this->assertNull($reservation->fresh()->hidden_at);
    }

    /**
     * Le drapeau lu par le front est le MIROIR EXACT de la règle serveur : si
     * les deux divergeaient, l'écran proposerait un bouton qui échoue.
     */
    public function test_le_drapeau_hideable_dit_la_meme_chose_que_le_serveur(): void
    {
        $client = $this->client();
        ServiceRequest::factory()->create([
            'user_id' => $client->id,
            'status' => RequestStatus::CLOTURE->value,
        ]);
        ServiceRequest::factory()->create([
            'user_id' => $client->id,
            'status' => RequestStatus::VISITE->value,
        ]);

        Sanctum::actingAs($client);

        $reponse = $this->getJson('/api/v1/requests/my')->assertOk();
        $parStatut = collect($reponse->json('data'))->keyBy('status');

        $this->assertTrue($parStatut['cloture']['hideable']);
        $this->assertFalse($parStatut['visite']['hideable']);
    }

    // =========================================================================
    // 2 bis. Les fils et les notifications (demande explicite : « déjà vus ou lus »)
    // =========================================================================

    /**
     * ⚠️ **Le test qui protège le pivot.** Le masque d'un fil est porté par
     * `conversation_user`, pas par `conversations` : si quelqu'un le déplace un
     * jour sur le fil lui-même, le ménage du client ferait disparaître le fil de
     * la file de l'agent — c'est ici que ça se voit.
     */
    public function test_un_fil_range_par_le_client_reste_entier_chez_l_agent(): void
    {
        $client = $this->client();
        $agent = $this->agent();

        $fil = Conversation::create(['subject' => 'Question sur ma facture']);
        $fil->participants()->attach([$client->id, $agent->id]);

        Sanctum::actingAs($client);
        $this->getJson("/api/v1/messages/{$fil->id}")->assertOk();   // lu
        $this->postJson("/api/v1/messages/{$fil->id}/hide")->assertOk();

        // Le client ne le voit plus…
        $this->getJson('/api/v1/messages')->assertOk()->assertJsonCount(0, 'data');

        // …mais l'agent, si. Le fil n'a été ni supprimé ni clos.
        Sanctum::actingAs($agent);
        $this->getJson('/api/v1/messages')->assertOk()->assertJsonCount(1, 'data');
        $this->assertNull($fil->fresh()->closed_at);
    }

    /**
     * ⚠️ **Ranger n'est pas se taire.** Sans cette règle, la réponse d'un agent
     * atterrirait dans un fil invisible et personne ne comprendrait le silence
     * du client.
     */
    public function test_un_message_neuf_fait_revenir_le_fil_range(): void
    {
        $client = $this->client();
        $agent = $this->agent();

        $fil = Conversation::create(['subject' => 'Suivi de dossier']);
        $fil->participants()->attach([$client->id, $agent->id]);

        Sanctum::actingAs($client);
        $this->getJson("/api/v1/messages/{$fil->id}")->assertOk();
        $this->postJson("/api/v1/messages/{$fil->id}/hide")->assertOk();
        $this->getJson('/api/v1/messages')->assertOk()->assertJsonCount(0, 'data');

        // L'agent répond : le fil ressort tout seul de la corbeille du client.
        Sanctum::actingAs($agent);
        $this->postJson("/api/v1/messages/{$fil->id}/messages", ['body' => 'Voici la réponse.'])
            ->assertCreated();

        Sanctum::actingAs($client);
        $this->getJson('/api/v1/messages')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/me/trash')->assertOk()->assertJsonCount(0, 'data.items');
    }

    public function test_un_fil_avec_des_messages_non_lus_ne_se_range_pas(): void
    {
        $client = $this->client();
        $agent = $this->agent();

        $fil = Conversation::create(['subject' => 'Une question en attente']);
        $fil->participants()->attach([$client->id, $agent->id]);

        Sanctum::actingAs($agent);
        $this->postJson("/api/v1/messages/{$fil->id}/messages", ['body' => 'Pouvez-vous confirmer ?'])
            ->assertCreated();

        // Le client n'a pas ouvert le fil : le ranger masquerait une question
        // qui attend SA réponse.
        Sanctum::actingAs($client);
        $this->postJson("/api/v1/messages/{$fil->id}/hide")->assertStatus(422);
        $this->getJson('/api/v1/messages')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_une_notification_lue_se_range_et_se_restaure_par_son_uuid(): void
    {
        $client = $this->client();
        $this->notifier($client);

        $notification = $client->notifications()->first();
        $notification->markAsRead();

        Sanctum::actingAs($client);

        $this->postJson("/api/v1/users/me/notifications/{$notification->id}/hide")->assertOk();
        $this->getJson('/api/v1/users/me/notifications')->assertOk()->assertJsonCount(0, 'data');

        // ⚠️ L'identifiant est un UUID : c'est le seul type de la corbeille qui
        // ne soit pas un entier, et la route a dû perdre son `whereNumber`.
        $this->getJson('/api/v1/me/trash')
            ->assertOk()
            ->assertJsonPath('data.items.0.type', 'notification')
            ->assertJsonPath('data.items.0.id', (string) $notification->id);

        $this->postJson("/api/v1/me/trash/notification/{$notification->id}/restore")->assertOk();
        $this->getJson('/api/v1/users/me/notifications')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_une_notification_non_lue_ne_se_range_pas(): void
    {
        $client = $this->client();
        $this->notifier($client);
        $notification = $client->notifications()->first();

        Sanctum::actingAs($client);
        $this->postJson("/api/v1/users/me/notifications/{$notification->id}/hide")
            ->assertStatus(422);

        // Elle reste dans la liste ET dans le compteur de non-lues.
        $this->getJson('/api/v1/users/me/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('unread_count', 1);
    }

    // =========================================================================
    // 3. Le retour : tel quel, sans rien réécrire
    // =========================================================================

    public function test_un_dossier_restaure_revient_dans_ma_liste_dans_l_etat_ou_je_l_ai_laisse(): void
    {
        $client = $this->client();
        $reservation = $this->reservation($client, BookingStatus::TERMINEE);

        Sanctum::actingAs($client);
        $this->postJson("/api/v1/bookings/{$reservation->id}/hide")->assertOk();

        $this->postJson("/api/v1/me/trash/booking/{$reservation->id}/restore")->assertOk();

        $this->getJson('/api/v1/bookings/my')->assertOk()->assertJsonCount(1, 'data');

        // ⚠️ Le statut n'a PAS été touché — contrairement à une annonce, qui
        // revient éteinte. Un dossier masqué n'a jamais cessé d'exister pour
        // Kaikun ni pour le partenaire : y toucher réécrirait un contrat.
        $this->assertSame(BookingStatus::TERMINEE, $reservation->fresh()->status);
        $this->assertNull($reservation->fresh()->hidden_at);
    }

    // =========================================================================
    // 4. Cloisonnement
    // =========================================================================

    public function test_on_ne_range_pas_le_dossier_de_quelqu_un_d_autre(): void
    {
        $proprietaire = $this->client();
        $curieux = $this->client();
        $demande = ServiceRequest::factory()->create([
            'user_id' => $proprietaire->id,
            'status' => RequestStatus::CLOTURE->value,
        ]);

        Sanctum::actingAs($curieux);
        $this->postJson("/api/v1/requests/{$demande->id}/hide")->assertStatus(403);

        $this->assertNull($demande->fresh()->hidden_at);
    }

    public function test_la_corbeille_d_un_client_ne_montre_pas_celle_d_un_autre(): void
    {
        $premier = $this->client();
        $second = $this->client();

        $demande = ServiceRequest::factory()->create([
            'user_id' => $premier->id,
            'status' => RequestStatus::CLOTURE->value,
        ]);

        Sanctum::actingAs($premier);
        $this->postJson("/api/v1/requests/{$demande->id}/hide")->assertOk();

        Sanctum::actingAs($second);
        $this->getJson('/api/v1/me/trash')->assertOk()->assertJsonCount(0, 'data.items');

        // ⚠️ Même réponse que « n'existe pas » : distinguer les deux dirait à un
        // curieux qu'un dossier existe bien à cet identifiant, chez un autre.
        $this->postJson("/api/v1/me/trash/request/{$demande->id}/restore")
            ->assertStatus(404);
    }
}
