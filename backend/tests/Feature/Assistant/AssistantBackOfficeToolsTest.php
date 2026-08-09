<?php

namespace Tests\Feature\Assistant;

use App\Enums\RequestStatus;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Immo\Enums\PropertyStatus;
use App\Modules\Immo\Models\Property;
use App\Modules\Stay\Models\Stay;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Outils du BACK-OFFICE (phase F10.3).
 *
 * F10.2 demandait « l'assistant ne répond-il qu'à la bonne personne ? ». Ici la
 * question change de nature, parce que ces outils montrent les dossiers **des
 * autres** : « l'assistant respecte-t-il la DÉLÉGATION ? »
 *
 * Depuis F7.1.b, le back-office ne distribue pas ses droits par rôle mais
 * personne par personne : un agent ne peut traiter que les dossiers qu'un super
 * administrateur lui a explicitement cochés. Un assistant qui n'ouvrirait ses
 * outils qu'au *rôle* rendrait cette matrice décorative. D'où la forme de ces
 * tests : pour chaque outil sensible, **le même agent est interrogé deux fois**,
 * avant et après la délégation. Un test qui ne vérifierait que le cas autorisé
 * passerait au vert sur un code qui ouvre tout à toute l'équipe.
 */
class AssistantBackOfficeToolsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/assistant/messages';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        RateLimiter::clear('assistant');
        Cache::forget('assistant:places');
    }

    /**
     * Crée un compte porteur du rôle demandé.
     */
    private function compte(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    /**
     * Envoie un message au nom d'un utilisateur et renvoie la réponse.
     */
    private function demander(User $user, string $message): array
    {
        Sanctum::actingAs($user);

        return $this->postJson(self::URL, ['message' => $message])
            ->assertOk()
            ->json('data.reply');
    }

    /** Règlement minimal rattaché à une réservation. */
    private function reglement(string $reference, string $status = 'complete'): Payment
    {
        $stay = Stay::factory()->create();
        $client = $this->compte(UserRole::CLIENT);

        $booking = Booking::create([
            'reference' => 'BK-'.substr($reference, -6),
            'user_id' => $client->id,
            'bookable_type' => Stay::class,
            'bookable_id' => $stay->id,
            'start_date' => today(),
            'end_date' => today()->addDays(2),
            'guests' => 2,
            'amount_xof' => 150_000,
            'status' => 'confirmee',
        ]);

        return Payment::create([
            'reference' => $reference,
            'booking_id' => $booking->id,
            'provider' => 'paytech',
            'amount_xof' => 150_000,
            'commission_xof' => 15_000,
            'kind' => 'integral',
            'status' => $status,
            'mode' => 'paytech',
        ]);
    }

    /** Fil de support ouvert, éventuellement assigné. */
    private function fil(string $subject, ?User $agent, User $client): Conversation
    {
        $fil = Conversation::create([
            'subject' => $subject,
            'assigned_agent_id' => $agent?->id,
            'last_message_at' => now(),
        ]);

        $fil->participants()->attach([$client->id, ...($agent ? [$agent->id] : [])]);

        Message::create([
            'conversation_id' => $fil->id,
            'sender_id' => $client->id,
            'body' => 'Bonjour, je voudrais un point sur mon dossier.',
        ]);

        return $fil;
    }

    // =========================================================================
    // LA DÉLÉGATION — le cœur de la tranche
    // =========================================================================

    /**
     * ⚠️ TEST CENTRAL DE F10.3 — la trousse suit la PERMISSION, pas le rôle.
     *
     * Le même agent, la même question, deux réponses : sans la délégation
     * `gerer:paiements` il n'obtient rien du règlement ; avec elle, il l'obtient.
     * C'est l'assurance que l'assistant n'est pas une porte dérobée sur la
     * matrice de droits construite en F7.1.b.
     */
    public function test_un_agent_sans_delegation_n_atteint_pas_les_paiements(): void
    {
        $agent = $this->compte(UserRole::AGENT_KAIKUN);
        $this->reglement('PAY-F103TEST');

        // Avant la délégation : aucun outil de paiement dans sa trousse.
        $avant = $this->demander($agent, 'où en est le règlement PAY-F103TEST ?');

        $this->assertNull($avant['tool']);
        $this->assertStringContainsString('droits', $avant['text']);
        // Rien du dossier ne doit avoir filtré dans le refus.
        $this->assertSame([], $avant['items']);

        // Après la délégation : le même message aboutit.
        $agent->givePermissionTo(AdminPermission::GERER_PAIEMENTS->value);
        $agent->forgetCachedPermissions();

        $apres = $this->demander($agent, 'où en est le règlement PAY-F103TEST ?');

        $this->assertSame('suivre_paiement', $apres['tool']);
        $this->assertSame('PAY-F103TEST', $apres['items'][0]['reference']);
    }

    /**
     * Même démonstration sur l'annuaire, gardé par une permission de
     * GOUVERNANCE : un agent pleinement outillé (toutes les permissions
     * opérationnelles) n'y accède toujours pas.
     */
    public function test_un_agent_operationnel_n_atteint_pas_l_annuaire_des_comptes(): void
    {
        $agent = $this->compte(UserRole::AGENT_KAIKUN);
        $agent->givePermissionTo(AdminPermission::operational());
        $agent->forgetCachedPermissions();

        $cible = User::factory()->create(['name' => 'Fatou Ndiaye']);

        $reply = $this->demander($agent, 'retrouve-moi le compte de Fatou Ndiaye');

        $this->assertNull($reply['tool']);
        $this->assertStringNotContainsString($cible->email, json_encode($reply));
    }

    /**
     * Le super administrateur n'a AUCUNE permission assignée : il passe par
     * `Gate::before`. C'est le piège de F7.4.a, où un rail vide lui avait été
     * servi — la trousse doit donc lui être ouverte en entier.
     */
    public function test_le_super_admin_recoit_la_trousse_complete(): void
    {
        $super = $this->compte(UserRole::SUPER_ADMIN);
        $this->assertEmpty($super->getDirectPermissions());

        $this->reglement('PAY-SUPERADM');

        $this->assertSame(
            'suivre_paiement',
            $this->demander($super, 'le paiement PAY-SUPERADM est-il passé ?')['tool'],
        );
        $this->assertSame(
            'rechercher_compte',
            $this->demander($super, 'retrouve-moi le compte de Ndiaye')['tool'],
        );
    }

    /**
     * ⚠️ Un client ne doit jamais toucher un outil de back-office, même en en
     * employant le vocabulaire exact. Le registre ne les lui présente pas — sa
     * question repart donc dans les règles publiques.
     */
    public function test_un_client_n_atteint_aucun_outil_back_office(): void
    {
        $client = $this->compte(UserRole::CLIENT);
        Property::factory()->create(['status' => PropertyStatus::EN_ATTENTE_VALIDATION->value]);

        foreach ([
            'quelle est la file de validation ?',
            'montre-moi le tableau de bord de la plateforme',
            'retrouve-moi le compte de Fatou',
        ] as $message) {
            $reply = $this->demander($client, $message);

            $this->assertNotContains($reply['tool'], [
                'file_validation', 'activite_plateforme', 'rechercher_compte',
                'suivre_paiement', 'demandes_a_traiter', 'fils_support',
            ], "Un client a atteint un outil back-office avec : {$message}");
        }
    }

    // =========================================================================
    // LECTURE SEULE
    // =========================================================================

    /**
     * ⚠️ Aucun outil de la tranche n'écrit. Le test le prouve sur le geste le
     * plus tentant à automatiser — valider un bien — en vérifiant qu'après la
     * question le bien est TOUJOURS en attente.
     */
    public function test_aucun_outil_back_office_ne_modifie_les_dossiers(): void
    {
        $admin = $this->compte(UserRole::ADMIN);
        $bien = Property::factory()->create(['status' => PropertyStatus::EN_ATTENTE_VALIDATION->value]);
        $demande = ServiceRequest::factory()->create(['status' => RequestStatus::RECU->value]);

        $this->demander($admin, 'que reste-t-il à valider ?');
        $this->demander($admin, 'quelles demandes restent à traiter ?');

        $this->assertSame(PropertyStatus::EN_ATTENTE_VALIDATION, $bien->fresh()->status);
        $this->assertSame(RequestStatus::RECU, $demande->fresh()->status);
    }

    // =========================================================================
    // LES OUTILS, UN PAR UN
    // =========================================================================

    public function test_la_file_de_validation_compte_ce_qui_attend(): void
    {
        $admin = $this->compte(UserRole::ADMIN);

        Property::factory()->count(2)->create(['status' => PropertyStatus::EN_ATTENTE_VALIDATION->value]);
        Property::factory()->create(['status' => PropertyStatus::PUBLIE->value]);

        $reply = $this->demander($admin, 'que reste-t-il à valider ?');

        $this->assertSame('file_validation', $reply['tool']);
        $this->assertStringContainsString('2', $reply['text']);

        $biens = collect($reply['items'])->firstWhere('titre', 'Biens immobiliers');
        // Le bien PUBLIÉ ne doit pas gonfler la file.
        $this->assertSame('2', $biens['statut']);
    }

    public function test_la_file_vide_le_dit_au_lieu_de_se_taire(): void
    {
        $admin = $this->compte(UserRole::ADMIN);

        $reply = $this->demander($admin, 'que reste-t-il à valider ?');

        $this->assertSame('file_validation', $reply['tool']);
        $this->assertSame([], $reply['items']);
        // Un cul-de-sac est interdit : il reste une porte de sortie.
        $this->assertNotEmpty($reply['actions']);
    }

    public function test_l_activite_du_jour_remonte_les_compteurs(): void
    {
        $admin = $this->compte(UserRole::ADMIN);
        ServiceRequest::factory()->create();

        $reply = $this->demander($admin, 'où en est la plateforme aujourd\'hui ?');

        $this->assertSame('activite_plateforme', $reply['tool']);
        $this->assertCount(3, $reply['items']);
        $this->assertSame("Reçu aujourd'hui", $reply['items'][1]['titre']);
    }

    public function test_la_recherche_de_compte_trouve_par_courriel(): void
    {
        $admin = $this->compte(UserRole::ADMIN);
        $cible = User::factory()->create(['name' => 'Aminata Sow', 'email' => 'aminata.sow@example.test']);

        $reply = $this->demander($admin, 'ouvre-moi le compte aminata.sow@example.test');

        $this->assertSame('rechercher_compte', $reply['tool']);
        $this->assertSame('Aminata Sow', $reply['items'][0]['titre']);
        $this->assertSame('/back-office/comptes/'.$cible->id, $reply['items'][0]['url']);
    }

    /**
     * ⚠️ La sortie reste FERMÉE : on peut chercher sur le téléphone, il ne
     * ressort pas. Confirmer une identité est légitime ; recracher les
     * coordonnées complètes dans une bulle qui reste affichée ne l'est pas.
     */
    public function test_la_recherche_de_compte_ne_divulgue_pas_le_telephone(): void
    {
        $admin = $this->compte(UserRole::ADMIN);
        User::factory()->create(['name' => 'Ousmane Ba', 'phone' => '771234567']);

        $reply = $this->demander($admin, 'le compte du 77 123 45 67');

        $this->assertSame('rechercher_compte', $reply['tool']);
        $this->assertSame('Ousmane Ba', $reply['items'][0]['titre']);
        $this->assertStringNotContainsString('771234567', json_encode($reply['items']));
    }

    public function test_la_recherche_de_compte_sans_terme_demande_a_preciser(): void
    {
        $admin = $this->compte(UserRole::ADMIN);
        User::factory()->count(3)->create();

        $reply = $this->demander($admin, 'ouvre-moi un compte');

        $this->assertSame('rechercher_compte', $reply['tool']);
        // Surtout pas l'annuaire entier obtenu sans l'avoir demandé.
        $this->assertSame([], $reply['items']);
        // ⚠️ Et pour la BONNE raison : « aucun résultat » et « précisez votre
        // recherche » donnent tous deux une liste vide. Sans cette assertion, le
        // test resterait vert alors que le terme extrait vaudrait « ouvre-moi »
        // et partirait en base — ce qui était exactement le cas avant le
        // correctif du trait d'union.
        $this->assertStringContainsString('Précisez', $reply['text']);
    }

    /**
     * ⚠️ RÉGRESSION F10.3, trouvée en curl et invisible aux tests précédents :
     * le trait d'union collait le verbe au nom cherché (« retrouve-moi Pierre
     * Robert »), et l'assistant semblait ignorer un client bien présent.
     */
    public function test_le_verbe_a_trait_d_union_ne_pollue_pas_le_nom_cherche(): void
    {
        $admin = $this->compte(UserRole::ADMIN);
        User::factory()->create(['name' => 'Pierre Robert']);

        $reply = $this->demander($admin, 'retrouve-moi le compte de Pierre Robert');

        $this->assertSame('rechercher_compte', $reply['tool']);
        $this->assertSame('Pierre Robert', $reply['items'][0]['titre']);
    }

    /**
     * ⚠️ RÉGRESSION F10.3, trouvée en curl : une référence à DEUX tirets
     * (`PAY-ACPT-…`, la forme des acomptes depuis F7.3.h) était tronquée par le
     * motif, puis rejetée — l'assistant réclamait une référence qu'on venait de
     * lui donner en entier.
     */
    public function test_une_reference_a_deux_tirets_est_reconnue(): void
    {
        $admin = $this->compte(UserRole::ADMIN);
        $this->reglement('PAY-ACPT-6YRYXV');

        $reply = $this->demander($admin, 'où en est le paiement PAY-ACPT-6YRYXV ?');

        $this->assertSame('suivre_paiement', $reply['tool']);
        $this->assertSame('PAY-ACPT-6YRYXV', $reply['items'][0]['reference']);
    }

    public function test_les_demandes_a_traiter_excluent_les_dossiers_clotures(): void
    {
        $admin = $this->compte(UserRole::ADMIN);

        ServiceRequest::factory()->create(['status' => RequestStatus::RECU->value, 'reference' => 'REQ-VIVANTE']);
        ServiceRequest::factory()->create(['status' => RequestStatus::CLOTURE->value, 'reference' => 'REQ-CLOSE']);

        $reply = $this->demander($admin, 'quelles demandes restent à traiter ?');

        $this->assertSame('demandes_a_traiter', $reply['tool']);
        $references = array_column($reply['items'], 'reference');
        $this->assertContains('REQ-VIVANTE', $references);
        $this->assertNotContains('REQ-CLOSE', $references);
    }

    /**
     * Les urgences d'abord : c'est la règle de la file, recopiée du contrôleur.
     */
    public function test_les_demandes_urgentes_passent_devant(): void
    {
        $admin = $this->compte(UserRole::ADMIN);

        ServiceRequest::factory()->create([
            'reference' => 'REQ-ANCIENNE', 'priority' => 'normale', 'created_at' => now()->subDays(9),
        ]);
        ServiceRequest::factory()->create([
            'reference' => 'REQ-URGENTE', 'priority' => 'urgente', 'created_at' => now(),
        ]);

        $reply = $this->demander($admin, 'quelles demandes restent à traiter ?');

        $this->assertSame('REQ-URGENTE', $reply['items'][0]['reference']);
    }

    /**
     * ⚠️ Un fil que personne n'a pris n'apparaît dans aucune boîte : le taire
     * ferait répondre « rien à traiter » pendant qu'un client attend dans le vide.
     */
    public function test_les_fils_support_signalent_ceux_que_personne_n_a_pris(): void
    {
        $agent = $this->compte(UserRole::AGENT_KAIKUN);
        $client = $this->compte(UserRole::CLIENT);

        $this->fil('Question sur ma réservation', $agent, $client);
        $this->fil('Personne ne me répond', null, $client);

        $reply = $this->demander($agent, 'quels messages attendent une réponse ?');

        $this->assertSame('fils_support', $reply['tool']);
        $this->assertCount(1, $reply['items']);
        $this->assertSame('Attend votre réponse', $reply['items'][0]['statut']);
        $this->assertStringContainsString('1 fil(s) que personne', $reply['text']);
    }

    /**
     * Un fil assigné à un COLLÈGUE n'est pas le mien : la boîte reste
     * personnelle, comme à l'écran.
     */
    public function test_les_fils_d_un_collegue_ne_remontent_pas(): void
    {
        $moi = $this->compte(UserRole::AGENT_KAIKUN);
        $collegue = $this->compte(UserRole::AGENT_KAIKUN);
        $client = $this->compte(UserRole::CLIENT);

        $this->fil('Le fil du collègue', $collegue, $client);

        $reply = $this->demander($moi, 'quels messages attendent une réponse ?');

        $this->assertSame([], $reply['items']);
        $this->assertStringNotContainsString('collègue', json_encode($reply));
    }

    /**
     * `repondre:messages` est portée par le RÔLE (F8.12.b) : tout agent est de
     * permanence d'office. Ce test verrouille cette exception au grant pur —
     * la retirer par erreur laisserait les fils s'entasser sans que personne
     * ne le voie.
     */
    public function test_tout_agent_atteint_la_boite_du_support_sans_delegation(): void
    {
        $agent = $this->compte(UserRole::AGENT_KAIKUN);
        $this->assertEmpty($agent->getDirectPermissions());

        $reply = $this->demander($agent, 'quels messages attendent une réponse ?');

        $this->assertSame('fils_support', $reply['tool']);
    }

    // =========================================================================
    // AIGUILLAGE DU CERVEAU
    // =========================================================================

    /**
     * ⚠️ Le mot « support » déclenche l'escalade vers un conseiller pour le
     * public (règle 1). Pour un agent, il désigne SA boîte de réception. C'est
     * exactement pourquoi la règle back-office passe en premier.
     */
    public function test_le_mot_support_ne_fait_pas_escalader_un_agent(): void
    {
        $agent = $this->compte(UserRole::AGENT_KAIKUN);

        $reply = $this->demander($agent, 'où en est le support ?');

        $this->assertSame('fils_support', $reply['tool']);
        $this->assertNotSame('contacter_support', $reply['tool']);
    }

    /**
     * L'inverse doit rester vrai : un membre de l'équipe qui cherche une villa
     * cherche bien une villa. La règle back-office ne doit pas avaler tout le
     * vocabulaire du site.
     */
    public function test_un_membre_de_l_equipe_peut_encore_chercher_au_catalogue(): void
    {
        $admin = $this->compte(UserRole::ADMIN);

        $reply = $this->demander($admin, 'je cherche une villa à Saly');

        $this->assertSame('rechercher_catalogue', $reply['tool']);
    }

    /**
     * Une référence collée seule, sans phrase, suffit : c'est le geste réel d'un
     * agent qui a le client au téléphone.
     */
    public function test_une_reference_seule_ouvre_le_reglement(): void
    {
        $admin = $this->compte(UserRole::ADMIN);
        $this->reglement('PAY-COLLEE1');

        $reply = $this->demander($admin, 'PAY-COLLEE1');

        $this->assertSame('suivre_paiement', $reply['tool']);
        $this->assertSame('PAY-COLLEE1', $reply['items'][0]['reference']);
    }
}
