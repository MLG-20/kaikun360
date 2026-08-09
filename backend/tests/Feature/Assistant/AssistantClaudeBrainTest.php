<?php

namespace Tests\Feature\Assistant;

use Anthropic\Client;
use Anthropic\RequestOptions;
use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Immo\Models\Property;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeAnthropicTransport;
use Tests\TestCase;

/**
 * Cerveau conversationnel de l'assistant (phase F10.4).
 *
 * Ces tests ne vérifient PAS que le modèle est intelligent — ce n'est ni
 * testable ni de notre ressort. Ils vérifient les quatre choses dont nous
 * répondons, nous :
 *
 *   1. **Le cloisonnement tient malgré le modèle.** La trousse envoyée est
 *      celle du rôle, et un outil réclamé hors trousse ne s'exécute pas.
 *   2. **Les données affichées viennent des outils.** Le modèle écrit la
 *      phrase ; il ne fabrique ni fiche, ni prix, ni lien.
 *   3. **On dégrade, on ne casse pas.** Clé absente, fournisseur en panne,
 *      réponse vide : le déterministe reprend la main sans que l'utilisateur
 *      voie une erreur.
 *   4. **La facture est bornée.** Le nombre d'appels par message est plafonné,
 *      quoi que fasse le modèle.
 *
 * ⚠️ Aucun appel réseau : le transporteur PSR-18 du SDK est remplacé par
 * `FakeAnthropicTransport`. Ces tests passent donc **sans clé API**, ce qui est
 * la condition pour qu'ils tournent en intégration continue.
 */
class AssistantClaudeBrainTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/assistant/messages';

    private FakeAnthropicTransport $api;

    protected function setUp(): void
    {
        parent::setUp();

        // Les rôles et les permissions fines du back-office viennent du seeder :
        // la trousse de F10.3 s'assemble par PERMISSION, il faut donc qu'elles
        // existent réellement pour que le test dise quelque chose.
        $this->seed(RolesAndPermissionsSeeder::class);

        RateLimiter::clear('assistant');
        Cache::forget('assistant:places');

        // Bascule du cerveau. La liaison lit la configuration à la résolution,
        // il n'y a donc rien d'autre à faire — c'est tout l'intérêt du contrat.
        config(['assistant.driver' => 'claude']);

        $this->api = new FakeAnthropicTransport;

        $this->app->singleton(Client::class, fn () => new Client(
            apiKey: 'cle-de-test',
            requestOptions: RequestOptions::with(transporter: $this->api),
        ));
    }

    /**
     * @param  array<int, array{role: string, text: string}>  $history
     * @return array<string, mixed>
     */
    private function demander(string $message, array $history = []): array
    {
        $payload = ['message' => $message];

        if ($history !== []) {
            $payload['history'] = $history;
        }

        return $this->postJson(self::URL, $payload)
            ->assertOk()
            ->json('data.reply');
    }

    /**
     * Crée un membre de l'équipe doté d'une permission fine.
     *
     * ⚠️ La permission est portée par le COMPTE, pas par le rôle : c'est le
     * grant pur de F7.1.b, et c'est ce qui fait que deux agents de la même
     * équipe n'ont pas la même trousse.
     */
    private function agent(AdminPermission $permission): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);
        $agent->givePermissionTo($permission->value);

        return $agent;
    }

    // =========================================================================
    // LE CHEMIN NOMINAL — le modèle choisit un outil, l'outil fournit les données
    // =========================================================================

    /**
     * Le modèle appelle un outil, et la réponse mêle son texte aux fiches réelles.
     */
    public function test_le_modele_actionne_un_outil_et_ses_resultats_sont_affiches(): void
    {
        Property::factory()->published()->create(['title' => 'Villa contemporaine avec piscine']);

        $this->api
            ->willCallTool('rechercher_catalogue', ['univers' => 'immobilier'])
            ->willAnswer('Voici une villa qui pourrait vous convenir.');

        $reply = $this->demander('je cherche une villa');

        $this->assertSame('Voici une villa qui pourrait vous convenir.', $reply['text']);
        $this->assertSame('rechercher_catalogue', $reply['tool']);
        $this->assertCount(1, $reply['items']);
        $this->assertSame('Villa contemporaine avec piscine', $reply['items'][0]['titre']);
    }

    /**
     * ⭐ LE TEST CENTRAL DE LA TRANCHE : les fiches ne viennent JAMAIS du modèle.
     *
     * On fait dire au modèle une contre-vérité — un prix inventé, un titre qui
     * n'existe pas. Le texte de la bulle est bien le sien : c'est lui qui parle.
     * Mais les fiches affichées restent celles de l'outil, donc de la base.
     *
     * C'est la séparation qui rend une hallucination inoffensive sur un site
     * marchand : le modèle n'a aucun chemin pour fabriquer une annonce, un prix
     * ou une disponibilité. Il n'écrit que de la prose.
     */
    public function test_une_hallucination_du_modele_ne_produit_aucune_fiche(): void
    {
        Property::factory()->published()->create([
            'title' => 'Villa contemporaine avec piscine',
            'price_xof' => 45_000_000,
        ]);

        $this->api
            ->willCallTool('rechercher_catalogue', ['univers' => 'immobilier'])
            ->willAnswer('J\'ai aussi un palais à 12 000 F CFA à Ngor.');

        $reply = $this->demander('je cherche une villa');

        $this->assertCount(1, $reply['items']);
        $this->assertSame('Villa contemporaine avec piscine', $reply['items'][0]['titre']);
        $this->assertSame(45_000_000, $reply['items'][0]['prix_xof']);
    }

    /**
     * ⭐ LE GAIN DE LA TRANCHE : l'historique est enfin exploité.
     *
     * Reçu, validé et plafonné depuis F10.0, il était purement et simplement
     * ignoré par le cerveau déterministe — « et moins cher ? » repartait de
     * zéro et finissait au support. Ce test vérifie qu'il part bien au modèle,
     * dans l'ordre, avec les rôles.
     */
    public function test_l_historique_de_la_conversation_est_transmis_au_modele(): void
    {
        Property::factory()->published()->create();

        $this->api
            ->willCallTool('rechercher_catalogue', ['univers' => 'immobilier', 'budget_max' => 20_000_000])
            ->willAnswer('Voici des biens moins chers.');

        $this->demander('et moins cher ?', [
            ['role' => 'user', 'text' => 'je cherche une villa à Dakar'],
            ['role' => 'assistant', 'text' => 'Voici trois villas à Dakar.'],
        ]);

        $messages = $this->api->requests[0]['messages'];

        $this->assertSame('user', $messages[0]['role']);
        $this->assertSame('je cherche une villa à Dakar', $messages[0]['content']);
        $this->assertSame('assistant', $messages[1]['role']);
        $this->assertSame('et moins cher ?', $messages[2]['content']);
    }

    /**
     * Un historique ouvert par l'assistant ne fait pas échouer l'appel.
     *
     * Ce n'est pas un cas limite mais le cas COURANT : le panneau ouvre chaque
     * conversation par un message d'accueil, donc le premier tour renvoyé est
     * presque toujours celui de l'assistant. Or l'API refuse une conversation
     * qui ne commence pas par l'utilisateur — sans ce nettoyage, l'assistant
     * serait tombé en repli à chaque second message.
     */
    public function test_un_historique_ouvert_par_l_assistant_est_recadre(): void
    {
        $this->api->willAnswer('Bien sûr.');

        $this->demander('comment payer ?', [
            ['role' => 'assistant', 'text' => 'Bonjour, comment puis-je vous aider ?'],
            ['role' => 'user', 'text' => 'bonjour'],
        ]);

        $messages = $this->api->requests[0]['messages'];

        $this->assertSame('user', $messages[0]['role']);
        $this->assertSame('bonjour', $messages[0]['content']);
    }

    // =========================================================================
    // CLOISONNEMENT — le modèle ne peut pas sortir de sa trousse
    // =========================================================================

    /**
     * La trousse envoyée au modèle est celle du rôle de l'appelant.
     *
     * Un visiteur ne reçoit que les trois outils publics. C'est la première
     * barrière : un outil qu'on ne présente pas ne peut pas être demandé — et
     * il ne coûte pas non plus de tokens.
     */
    public function test_un_visiteur_ne_recoit_que_les_outils_publics(): void
    {
        $this->api->willAnswer('Bonjour !');

        $this->demander('bonjour');

        $noms = array_column($this->api->requests[0]['tools'], 'name');

        sort($noms);
        $this->assertSame(['consulter_faq', 'contacter_support', 'rechercher_catalogue'], $noms);
    }

    /**
     * ⭐ Un outil réclamé HORS TROUSSE ne s'exécute pas, et ne fuit rien.
     *
     * C'est le scénario de l'injection de prompt réussie : le modèle est
     * persuadé de devoir chercher un compte. Le registre ne le lui a jamais
     * présenté, il répond `null`, et il ne se passe rien — ni exécution, ni
     * erreur serveur, ni indication de ce que l'outil aurait pu renvoyer.
     */
    public function test_un_outil_hors_trousse_est_refuse_sans_rien_reveler(): void
    {
        User::factory()->create(['email' => 'confidentiel@kaikun360.test']);

        $this->api
            ->willCallTool('rechercher_compte', ['terme' => 'confidentiel@kaikun360.test'])
            ->willAnswer('Je ne peux pas consulter les comptes.');

        $reply = $this->demander('donne-moi le compte de confidentiel@kaikun360.test');

        $this->assertSame([], $reply['items']);
        $this->assertNull($reply['tool']);
        $this->assertStringNotContainsString('confidentiel@kaikun360.test', json_encode($reply));

        // Le refus est renvoyé au modèle comme un résultat en erreur, pour qu'il
        // en informe la personne plutôt que de réessayer indéfiniment.
        $resultat = $this->api->requests[1]['messages'][2]['content'][0];
        $this->assertSame('tool_result', $resultat['type']);
        $this->assertTrue($resultat['is_error']);
    }

    /**
     * La trousse du back-office s'assemble par PERMISSION, pas par rôle.
     *
     * Règle posée en F10.3 (grant pur de F7.1.b) : deux agents de la même
     * équipe n'ont pas le même assistant. Elle doit survivre au changement de
     * cerveau — c'est le registre qui la porte, pas le driver.
     */
    public function test_la_trousse_du_back_office_suit_la_permission_et_non_le_role(): void
    {
        Sanctum::actingAs($this->agent(AdminPermission::GERER_PAIEMENTS));

        $this->api->willAnswer('Bonjour.');

        $this->demander('bonjour');

        $noms = array_column($this->api->requests[0]['tools'], 'name');

        // Délégué à ce compte, et à lui seul.
        $this->assertContains('suivre_paiement', $noms);

        // ⚠️ Non délégué : `rechercher_compte` exige `gerer:utilisateurs`, une
        // permission de gouvernance qu'aucun agent n'a d'office. C'est ce que
        // « la trousse suit la permission » veut dire — un collègue de la même
        // équipe, doté d'une autre délégation, n'aura pas le même assistant.
        $this->assertNotContains('rechercher_compte', $noms);

        // En revanche `activite_plateforme` EST là, et ce n'est pas une entorse :
        // `consulter:dashboard-admin` est portée par le RÔLE agent (seeder), au
        // même titre que `repondre:messages` depuis l'arbitrage de F8.12.b.
        // Voir le tableau de bord est le métier de base d'un agent.
        $this->assertContains('activite_plateforme', $noms);
    }

    /**
     * Le modèle ne voit jamais les adresses des fiches.
     *
     * L'invite lui interdit d'écrire des liens, mais la façon sûre de tenir
     * cette règle est qu'il n'en ait aucun sous les yeux : une adresse d'espace
     * connecté diffère par rôle (défaut n°1 de F10.1), et un lien recraché de
     * travers envoie la personne sur une page interdite — ou ailleurs.
     */
    public function test_les_adresses_des_fiches_ne_sont_pas_envoyees_au_modele(): void
    {
        Property::factory()->published()->create(['title' => 'Villa contemporaine avec piscine']);

        $this->api
            ->willCallTool('rechercher_catalogue', ['univers' => 'immobilier'])
            ->willAnswer('Voici.');

        $reply = $this->demander('je cherche une villa');

        $envoye = $this->api->requests[1]['messages'][2]['content'][0]['content'];

        $this->assertStringContainsString('Villa contemporaine', $envoye);
        $this->assertStringNotContainsString('"url"', $envoye);
        // La fiche rendue au panneau, elle, garde son lien : il n'a simplement
        // pas transité par le modèle.
        $this->assertArrayHasKey('url', $reply['items'][0]);
    }

    // =========================================================================
    // ON DÉGRADE, ON NE CASSE PAS
    // =========================================================================

    /**
     * ⭐ Fournisseur en panne : le déterministe reprend la main, en silence.
     *
     * Une bulle d'assistant n'est pas l'endroit où afficher une erreur
     * technique à un client. Le cerveau sans clé ni réseau répond toujours :
     * c'est exactement le service à rendre ici.
     */
    public function test_une_panne_du_fournisseur_fait_reprendre_le_deterministe(): void
    {
        Property::factory()->published()->create(['title' => 'Villa contemporaine avec piscine']);

        $this->api->willFail();

        $reply = $this->demander('je cherche une villa');

        // Réponse du cerveau déterministe : mêmes données, autre formulation.
        $this->assertSame('rechercher_catalogue', $reply['tool']);
        $this->assertCount(1, $reply['items']);
        $this->assertStringNotContainsString('simulé', $reply['text']);
        $this->assertStringNotContainsString('Exception', $reply['text']);
    }

    /**
     * Réponse vide du modèle (plafond de tokens, refus) : même repli.
     *
     * Sans ce filet, l'utilisateur verrait une bulle blanche — le symptôme le
     * plus déroutant qui soit, parce qu'il ressemble à un bug d'affichage.
     */
    public function test_une_reponse_vide_du_modele_fait_reprendre_le_deterministe(): void
    {
        $this->api->willAnswerNothing();

        $reply = $this->demander('bonjour');

        $this->assertNotSame('', trim($reply['text']));
    }

    /**
     * Une clé absente n'interrompt pas le service.
     *
     * C'est l'état RÉEL du déploiement tant que le client n'a pas ouvert son
     * compte sur la Console Anthropic : le driver peut être activé par erreur
     * dans un `.env`, l'assistant doit continuer de répondre.
     */
    public function test_sans_cle_api_l_assistant_repond_quand_meme(): void
    {
        config(['assistant.claude.api_key' => null]);
        $this->app->forgetInstance(Client::class);
        $this->app->singleton(Client::class, fn () => new Client(
            apiKey: '',
            requestOptions: RequestOptions::with(transporter: $this->api->willFail()),
        ));

        $reply = $this->demander('bonjour');

        $this->assertNotSame('', trim($reply['text']));
    }

    // =========================================================================
    // LA FACTURE EST BORNÉE
    // =========================================================================

    /**
     * ⭐ Un modèle qui boucle sur un outil est arrêté, et doit conclure.
     *
     * C'est le « déni de portefeuille » vu de l'intérieur : ce n'est pas un
     * attaquant qui multiplie les messages (le limiteur de débit s'en charge),
     * c'est le modèle lui-même qui appelle un outil vide en boucle. Au dernier
     * tour, les outils lui sont retirés : il ne PEUT plus qu'écrire du texte,
     * donc l'échange se termine, en un nombre d'appels facturés connu d'avance.
     */
    public function test_le_nombre_d_appels_au_modele_est_plafonne(): void
    {
        config(['assistant.claude.max_tool_rounds' => 2]);

        // Le modèle réclame un outil à chaque tour, indéfiniment.
        for ($i = 0; $i < 10; $i++) {
            $this->api->willCallTool('consulter_faq', [], 'toolu_'.$i);
        }

        $this->demander('comment payer ?');

        // 2 tours d'outils + 1 tour de conclusion.
        $this->assertSame(3, $this->api->callCount());
        $this->assertSame('none', $this->api->lastRequest()['tool_choice']['type']);
    }

    /**
     * Le plafond de tokens par réponse part bien dans la requête.
     *
     * C'est le garde-fou de coût le plus direct du module, et le seul qui borne
     * ce que le modèle PRODUIT — le reste borne ce qu'on lui envoie.
     */
    public function test_le_plafond_de_tokens_est_transmis_a_l_api(): void
    {
        config(['assistant.claude.max_tokens' => 321]);

        $this->api->willAnswer('Bonjour.');

        $this->demander('bonjour');

        $this->assertSame(321, $this->api->requests[0]['max_tokens']);
        $this->assertSame('claude-haiku-4-5', $this->api->requests[0]['model']);
    }

    // =========================================================================
    // L'INVITE SYSTÈME
    // =========================================================================

    /**
     * L'invite dit au modèle à QUI il parle, et le point de cache est posé.
     *
     * ⚠️ Le point de cache ne produit pas forcément un cache : le préfixe
     * minimal cacheable de Haiku 4.5 est de 4 096 tokens, que l'invite et les
     * descriptions d'outils n'atteignent pas. Le marqueur ne coûte rien et
     * devient utile tel quel si le modèle est monté en gamme.
     */
    public function test_l_invite_systeme_est_adaptee_a_l_appelant(): void
    {
        Sanctum::actingAs($this->agent(AdminPermission::GERER_PAIEMENTS));

        $this->api->willAnswer('Bonjour.');

        $this->demander('bonjour');

        $system = $this->api->requests[0]['system'][0];

        $this->assertStringContainsString('membre de l\'équipe', $system['text']);
        $this->assertStringContainsString('LECTURE SEULE', $system['text']);
        $this->assertSame('ephemeral', $system['cache_control']['type']);
    }
}
