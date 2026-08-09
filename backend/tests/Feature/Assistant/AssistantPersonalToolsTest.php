<?php

namespace Tests\Feature\Assistant;

use App\Models\Booking;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Diaspora\Models\DiasporaProject;
use App\Modules\Immo\Models\Property;
use App\Modules\Pro\Models\Provider;
use App\Modules\Pro\Models\ProviderMission;
use App\Modules\Stay\Models\Stay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Outils des espaces connectés (phase F10.2).
 *
 * Ces outils franchissent la frontière que les trois premiers ne franchissaient
 * pas : ils lisent des **données personnelles**. La question que ces tests
 * posent n'est donc pas « l'assistant répond-il ? » mais **« ne répond-il qu'à
 * la bonne personne ? »**.
 *
 * D'où leur forme : pour chaque outil, on crée systématiquement le dossier d'un
 * TIERS à côté de celui de l'appelant. Un test qui ne vérifierait que « je vois
 * mon dossier » passerait au vert sur un code qui montre aussi celui du voisin.
 */
class AssistantPersonalToolsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/assistant/messages';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('assistant');
        Cache::forget('assistant:places');
    }

    /**
     * Crée un compte porteur du rôle demandé.
     */
    private function compte(UserRole $role): User
    {
        Role::findOrCreate($role->value, 'web');
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

    /** Réservation minimale rattachée à un compte. */
    private function reservation(User $user, Stay $stay, string $reference): Booking
    {
        return Booking::create([
            'reference' => $reference,
            'user_id' => $user->id,
            'bookable_type' => Stay::class,
            'bookable_id' => $stay->id,
            'start_date' => today(),
            'end_date' => today()->addDays(2),
            'guests' => 2,
            'amount_xof' => 150_000,
            'status' => 'confirmee',
        ]);
    }

    // =========================================================================
    // CLOISONNEMENT — le cœur de la tranche
    // =========================================================================

    /**
     * ⚠️ TEST CENTRAL DE F10.2 — on ne voit QUE ses propres réservations.
     *
     * L'assistant est une seconde porte sur les mêmes données que l'espace
     * client. Si elle n'appliquait pas le même cloisonnement, il suffirait de
     * poser une question dans une bulle pour lire le dossier d'un inconnu.
     */
    public function test_un_client_ne_voit_que_ses_propres_reservations(): void
    {
        $stay = Stay::factory()->create();

        $moi = $this->compte(UserRole::CLIENT);
        $autre = $this->compte(UserRole::CLIENT);

        $this->reservation($moi, $stay, 'BK-A-MOI');
        $this->reservation($autre, $stay, 'BK-AU-VOISIN');

        $reply = $this->demander($moi, 'où en est ma réservation ?');

        $this->assertSame('mes_reservations', $reply['tool']);
        $this->assertCount(1, $reply['items']);
        $this->assertSame('BK-A-MOI', $reply['items'][0]['reference']);

        // Vérification par le corps entier : la fuite pourrait passer par la
        // phrase de synthèse et pas seulement par les fiches.
        $corps = json_encode($reply, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('BK-AU-VOISIN', $corps);
    }

    /**
     * Un propriétaire ne voit que SES biens — y compris non publiés.
     *
     * Double vérification : le bien du voisin est absent, et le bien en attente
     * de validation du propriétaire est bien présent (c'est tout l'intérêt de
     * l'outil : un bien invisible partout ailleurs, dont il croit le dépôt raté).
     */
    public function test_un_proprietaire_voit_ses_biens_meme_non_publies_et_jamais_ceux_d_un_autre(): void
    {
        $moi = $this->compte(UserRole::PROPRIETAIRE);
        $autre = $this->compte(UserRole::PROPRIETAIRE);

        Property::factory()->pending()->create([
            'owner_id' => $moi->id,
            'title' => 'Ma villa en attente',
        ]);
        Property::factory()->published()->create([
            'owner_id' => $autre->id,
            'title' => 'La villa du voisin',
        ]);

        $reply = $this->demander($moi, 'est-ce que mon annonce est en ligne ?');

        $this->assertSame('mes_biens', $reply['tool']);
        $this->assertCount(1, $reply['items']);
        $this->assertSame('Ma villa en attente', $reply['items'][0]['titre']);
        $this->assertStringNotContainsString(
            'La villa du voisin',
            json_encode($reply, JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * Un prestataire ne voit que ses missions.
     *
     * ⚠️ Ce test garde l'INDIRECTION : `provider_missions.provider_id` pointe
     * sur `providers`, pas sur `users`. Un `where('provider_id', $user->id)`
     * écrit de bonne foi compilerait et montrerait les missions d'un autre —
     * exactement le genre de fuite silencieuse qu'aucune erreur ne signale.
     */
    public function test_un_prestataire_ne_voit_que_ses_missions(): void
    {
        $moi = $this->compte(UserRole::PRESTATAIRE);
        $autre = $this->compte(UserRole::PRESTATAIRE);

        $monProfil = Provider::factory()->validated()->create(['user_id' => $moi->id]);
        $sonProfil = Provider::factory()->validated()->create(['user_id' => $autre->id]);

        ProviderMission::factory()->create([
            'provider_id' => $monProfil->id,
            'title' => 'Ma mission à moi',
        ]);
        ProviderMission::factory()->create([
            'provider_id' => $sonProfil->id,
            'title' => 'La mission du concurrent',
        ]);

        $reply = $this->demander($moi, 'quelles sont mes missions ?');

        $this->assertSame('mes_missions', $reply['tool']);
        $this->assertCount(1, $reply['items']);
        $this->assertSame('Ma mission à moi', $reply['items'][0]['titre']);
        $this->assertStringNotContainsString(
            'concurrent',
            json_encode($reply, JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * Un client ne voit que ses projets diaspora.
     */
    public function test_un_client_ne_voit_que_ses_projets_diaspora(): void
    {
        $moi = $this->compte(UserRole::CLIENT);
        $autre = $this->compte(UserRole::CLIENT);

        DiasporaProject::factory()->create([
            'client_id' => $moi->id,
            'reference' => 'DIA-A-MOI',
        ]);
        DiasporaProject::factory()->create([
            'client_id' => $autre->id,
            'reference' => 'DIA-AU-VOISIN',
        ]);

        $reply = $this->demander($moi, 'où en est mon projet diaspora ?');

        $this->assertSame('mes_projets_diaspora', $reply['tool']);
        $this->assertCount(1, $reply['items']);
        $this->assertSame('DIA-A-MOI', $reply['items'][0]['reference']);
    }

    /**
     * Un client ne voit que ses demandes.
     */
    public function test_un_client_ne_voit_que_ses_demandes(): void
    {
        $moi = $this->compte(UserRole::CLIENT);
        $autre = $this->compte(UserRole::CLIENT);

        ServiceRequest::factory()->create(['user_id' => $moi->id, 'reference' => 'REQ-A-MOI']);
        ServiceRequest::factory()->create(['user_id' => $autre->id, 'reference' => 'REQ-AU-VOISIN']);

        $reply = $this->demander($moi, 'ma demande a-t-elle été traitée ?');

        $this->assertSame('mes_demandes', $reply['tool']);
        $this->assertCount(1, $reply['items']);
        $this->assertSame('REQ-A-MOI', $reply['items'][0]['reference']);
    }

    // =========================================================================
    // TROUSSE À OUTILS — chaque rôle n'a que la sienne
    // =========================================================================

    /**
     * Un visiteur anonyme n'obtient rien de personnel — on l'invite à se
     * connecter plutôt que de le renvoyer au support.
     */
    public function test_un_visiteur_est_invite_a_se_connecter(): void
    {
        $reply = $this->postJson(self::URL, ['message' => 'où en est ma réservation ?'])
            ->assertOk()
            ->json('data.reply');

        $this->assertNull($reply['tool'], 'Aucun outil personnel ne doit s\'exécuter sans session.');
        $this->assertSame([], $reply['items']);
        $this->assertSame(
            '/auth/connexion',
            collect($reply['actions'])->firstWhere('kind', 'link')['payload']['url'],
        );
    }

    /**
     * Un client n'a pas d'outil « missions » : sa question poursuit son chemin
     * dans les autres règles au lieu de buter sur un refus.
     */
    public function test_un_client_n_a_pas_l_outil_missions(): void
    {
        $client = $this->compte(UserRole::CLIENT);

        $reply = $this->demander($client, 'quelles sont mes missions ?');

        $this->assertNotSame('mes_missions', $reply['tool']);
    }

    /**
     * ⚠️ Une ENTREPRISE n'a pas l'outil « demandes », malgré les apparences.
     *
     * Son espace a bien un écran « Mes demandes », mais il liste des **demandes
     * de team building**, pas des `ServiceRequest`. Ouvrir l'outil ici aurait
     * produit des fiches dont le lien pointe vers l'écran d'un autre registre :
     * au mieux une fiche introuvable, au pire la demande de team building qui
     * porte le même numéro. Deux écrans homonymes ne sont pas un écran commun.
     */
    public function test_une_entreprise_n_a_pas_l_outil_demandes(): void
    {
        $entreprise = $this->compte(UserRole::ENTREPRISE);
        ServiceRequest::factory()->create(['user_id' => $entreprise->id]);

        $reply = $this->demander($entreprise, 'où en est ma demande ?');

        $this->assertNotSame('mes_demandes', $reply['tool']);
    }

    /**
     * Un propriétaire n'a pas l'outil « réservations » (il n'en prend pas au
     * titre de son espace) : même règle de non-blocage.
     */
    public function test_un_proprietaire_n_a_pas_l_outil_reservations(): void
    {
        $proprietaire = $this->compte(UserRole::PROPRIETAIRE);

        $reply = $this->demander($proprietaire, 'où en est ma réservation ?');

        $this->assertNotSame('mes_reservations', $reply['tool']);
    }

    // =========================================================================
    // COMPRÉHENSION — le possessif sépare deux intentions opposées
    // =========================================================================

    /**
     * ⚠️ « je cherche une villa » reste une RECHERCHE, pas un dossier.
     *
     * Sans l'exigence d'un mot de possession, la détection des dossiers
     * personnels avalerait la moitié des recherches du site, et l'assistant
     * répondrait « vous n'avez aucune réservation » à quelqu'un qui voulait
     * précisément en faire une.
     */
    public function test_une_recherche_ne_bascule_pas_sur_les_dossiers_personnels(): void
    {
        $client = $this->compte(UserRole::CLIENT);

        $reply = $this->demander($client, 'je cherche une villa à louer');

        $this->assertSame('rechercher_catalogue', $reply['tool']);
    }

    /**
     * ⚠️ Non-régression du faux positif de F10.0 : « je voudrais **bien**
     * savoir comment payer » ne doit pas partir dans « mes biens », alors que
     * « bien » est redevenu un mot-clé de cette famille.
     *
     * C'est le possessif qui fait la différence — la phrase n'en a aucun.
     */
    public function test_l_adverbe_bien_ne_declenche_pas_mes_biens(): void
    {
        $proprietaire = $this->compte(UserRole::PROPRIETAIRE);

        $reply = $this->demander($proprietaire, 'je voudrais bien savoir comment payer');

        $this->assertNotSame('mes_biens', $reply['tool']);
        $this->assertSame('consulter_faq', $reply['tool']);
    }

    /**
     * Un dossier nommé passe AVANT le catalogue.
     *
     * « ma réservation de villa » contient « villa » : traité par la règle du
     * catalogue, il aurait renvoyé des annonces à vendre à quelqu'un qui
     * demandait des nouvelles de son séjour.
     */
    public function test_un_dossier_nomme_passe_avant_le_catalogue(): void
    {
        $stay = Stay::factory()->create();
        $client = $this->compte(UserRole::CLIENT);
        $this->reservation($client, $stay, 'BK-PRIORITE');

        $reply = $this->demander($client, 'où en est ma réservation de villa ?');

        $this->assertSame('mes_reservations', $reply['tool']);
    }

    // =========================================================================
    // LECTURE SEULE
    // =========================================================================

    /**
     * ⚠️ Aucun outil de F10.2 n'écrit quoi que ce soit.
     *
     * La règle du module tient tant que personne ne la contourne « juste une
     * fois » : consulter ses dossiers ne doit modifier ni statut, ni date, ni
     * compteur.
     */
    public function test_consulter_ses_dossiers_n_ecrit_rien(): void
    {
        $stay = Stay::factory()->create();
        $client = $this->compte(UserRole::CLIENT);
        $reservation = $this->reservation($client, $stay, 'BK-LECTURE');

        $avant = $reservation->updated_at;

        $this->demander($client, 'où en est ma réservation ?');

        $this->assertEquals($avant, $reservation->fresh()->updated_at);
        $this->assertDatabaseCount('conversations', 0);
    }

    /**
     * Les fiches ne portent que des champs d'affichage.
     *
     * Un dossier personnel contient des identifiants techniques et parfois les
     * coordonnées d'un tiers : rien de tout cela n'a à traverser une bulle.
     */
    public function test_les_fiches_personnelles_ne_portent_aucun_champ_interne(): void
    {
        $stay = Stay::factory()->create();
        $client = $this->compte(UserRole::CLIENT);
        $this->reservation($client, $stay, 'BK-CHAMPS');

        $reply = $this->demander($client, 'où en est ma réservation ?');

        $this->assertEqualsCanonicalizing(
            ['reference', 'titre', 'statut', 'periode', 'montant', 'url'],
            array_keys($reply['items'][0]),
        );
    }

    // =========================================================================
    // ESCALADE — la seule trace qui remonte au back-office (F10.2)
    // =========================================================================

    /**
     * Le fil de support ouvert depuis l'assistant s'annonce comme tel.
     *
     * C'est toute la « journalisation » de la tranche, et c'est un arbitrage :
     * aucune conversation n'est stockée. L'agent doit néanmoins savoir d'où
     * vient la demande — deux fils identiques, l'un tapé dans la messagerie et
     * l'autre passé par l'assistant, ne s'instruisent pas pareil, et le second
     * signale une question que l'assistant n'a pas su traiter.
     */
    public function test_l_escalade_s_annonce_comme_venant_de_l_assistant(): void
    {
        $client = $this->compte(UserRole::CLIENT);

        $reply = $this->demander($client, 'je veux parler à un conseiller');

        $support = collect($reply['actions'])->firstWhere('kind', 'support');

        $this->assertNotNull($support);
        $this->assertStringStartsWith('Assistant — ', $support['payload']['subject']);
        // ⚠️ Le CORPS reste les mots exacts de la personne : on habille
        // l'étiquette, jamais le contenu que l'agent doit lire.
        $this->assertSame('je veux parler à un conseiller', $support['payload']['body']);
    }
}
