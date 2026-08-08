<?php

namespace Tests\Feature\Assistant;

use App\Models\Faq;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Immo\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Outils publics de l'assistant (phase F10.0).
 *
 * Le cœur de ces tests n'est pas « l'assistant répond-il ? » mais « répond-il
 * à partir des VRAIES données, et seulement de celles qu'il a le droit de
 * montrer ? ». C'est ce qui sépare cet assistant de celui du prototype, qui
 * récitait des phrases écrites en dur.
 */
class AssistantToolsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/assistant/messages';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('assistant');

        // La liste des lieux est mise en cache une heure par le cerveau : sans
        // purge, un test hériterait du référentiel construit par le précédent.
        Cache::forget('assistant:places');
    }

    /**
     * Envoie un message et renvoie la réponse de l'assistant.
     */
    private function demander(string $message): array
    {
        return $this->postJson(self::URL, ['message' => $message])
            ->assertOk()
            ->json('data.reply');
    }

    // =========================================================================
    // CATALOGUE — les annonces viennent de la base, publiées uniquement
    // =========================================================================

    /**
     * Une recherche immobilière remonte les biens PUBLIÉS.
     */
    public function test_recherche_immobiliere_remonte_les_biens_publies(): void
    {
        Property::factory()->published()->create(['title' => 'Villa contemporaine avec piscine']);

        $reply = $this->demander('je cherche une villa');

        $this->assertSame('rechercher_catalogue', $reply['tool']);
        $this->assertCount(1, $reply['items']);
        $this->assertSame('Villa contemporaine avec piscine', $reply['items'][0]['titre']);
    }

    /**
     * Un lieu TOURISTIQUE est compris, au même titre qu'une commune.
     *
     * Défaut trouvé en F10.1 en interrogeant le serveur réel : le vocabulaire du
     * cerveau ne contenait que les communes et les régions, alors que la
     * recherche sait filtrer sur `tourist_zone` et sur `destination`. « Saly »,
     * « Casamance » ou « Gorée » n'étaient donc jamais transmis — l'assistant
     * répondait par les trois derniers circuits publiés, n'importe où dans le
     * pays, en ayant l'air d'avoir compris.
     */
    public function test_une_destination_touristique_est_comprise(): void
    {
        TourismExperience::factory()->published()->create([
            'title' => 'Trois jours en Casamance',
            'destination' => 'Casamance',
        ]);
        TourismExperience::factory()->published()->create([
            'title' => 'Escapade à Lompoul',
            'destination' => 'Lompoul',
        ]);

        $reply = $this->demander('je voudrais un circuit en Casamance');

        $this->assertSame('rechercher_catalogue', $reply['tool']);
        $this->assertCount(1, $reply['items']);
        $this->assertSame('Trois jours en Casamance', $reply['items'][0]['titre']);
        // Le lieu est repris dans la phrase : c'est ce qui prouve à la personne
        // qu'elle a été comprise.
        $this->assertStringContainsString('Casamance', $reply['text']);
        // ⚠️ Accord du verbe sur un résultat unique (« qui correspond »).
        $this->assertStringNotContainsString('correspondent', $reply['text']);
    }

    /**
     * Le vocabulaire des lieux ne trahit pas une annonce NON publiée.
     *
     * Corollaire de la règle centrale du module : si un circuit en attente de
     * validation entrait dans le référentiel des lieux, l'assistant reconnaîtrait
     * une destination que le catalogue public ignore — et le dirait, en
     * répondant « du côté de X » sur une annonce que personne ne doit voir.
     */
    public function test_le_vocabulaire_des_lieux_ignore_les_annonces_non_publiees(): void
    {
        TourismExperience::factory()->create([
            'title' => 'Circuit confidentiel',
            'destination' => 'Kédougou',
            'published_at' => null,
        ]);

        $reply = $this->demander('je voudrais un circuit à Kédougou');

        $this->assertSame([], $reply['items']);
        $this->assertStringNotContainsString('Kédougou', $reply['text']);
    }

    /**
     * ⚠️ TEST CENTRAL DU MODULE — un bien NON publié ne fuite jamais.
     *
     * Le catalogue public garantit déjà qu'un bien en attente, rejeté ou
     * suspendu reste invisible. L'assistant est une seconde porte sur les mêmes
     * données : si elle n'appliquait pas la même règle, tout le travail de
     * validation des annonces (F7) serait contournable en posant une question
     * dans une bulle de discussion.
     */
    public function test_un_bien_non_publie_ne_fuite_pas_par_assistant(): void
    {
        Property::factory()->pending()->create(['title' => 'Villa en attente de validation']);
        Property::factory()->rejected()->create(['title' => 'Villa rejetée par un agent']);

        $reply = $this->demander('je cherche une villa');

        $this->assertSame([], $reply['items'], 'Aucune annonce non publiée ne doit remonter.');

        // Vérification par le texte : la fuite pourrait passer par la phrase de
        // synthèse et pas seulement par les fiches.
        $corps = json_encode($reply, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('en attente de validation', $corps);
        $this->assertStringNotContainsString('rejetée', $corps);
    }

    /**
     * Un budget exprimé en toutes lettres filtre les résultats.
     */
    public function test_le_budget_filtre_les_resultats(): void
    {
        Property::factory()->published()->create(['title' => 'Terrain abordable', 'price_xof' => 15_000_000]);
        Property::factory()->published()->create(['title' => 'Terrain hors budget', 'price_xof' => 90_000_000]);

        $reply = $this->demander('je veux un terrain à 20 millions');

        $titres = array_column($reply['items'], 'titre');

        $this->assertContains('Terrain abordable', $titres);
        $this->assertNotContains('Terrain hors budget', $titres);
    }

    /**
     * Les fiches ne portent que des champs destinés à l'affichage.
     *
     * Parade à l'exfiltration par la réponse : même détourné, le cerveau ne
     * peut recracher que ce qui a traversé ToolResult.
     */
    public function test_les_fiches_ne_portent_aucun_champ_interne(): void
    {
        Property::factory()->published()->create(['title' => 'Villa témoin']);

        $reply = $this->demander('je cherche une villa');

        $champs = array_keys($reply['items'][0]);

        $this->assertEqualsCanonicalizing(
            ['id', 'titre', 'lieu', 'prix_xof', 'unite', 'url'],
            $champs,
            'Une fiche ne doit exposer que les champs prévus pour l\'affichage.',
        );
    }

    /**
     * Catalogue vide : l'assistant le dit et propose une sortie.
     *
     * Un assistant qui répond « rien trouvé » et s'arrête est un cul-de-sac.
     */
    public function test_catalogue_vide_propose_une_sortie(): void
    {
        $reply = $this->demander('je cherche un appartement');

        $this->assertSame([], $reply['items']);
        $this->assertNotEmpty($reply['actions'], 'Une réponse vide doit toujours offrir une action.');
    }

    /**
     * Un nombre qui n'est PAS un prix ne doit pas devenir un filtre de budget.
     *
     * Régression corrigée après revue : « pour 10 personnes » était lu comme un
     * budget de 10 F CFA, donc zéro résultat — et l'utilisateur en concluait que
     * le catalogue était vide. Le faux positif est ici bien pire que l'absence
     * de filtre.
     */
    public function test_un_nombre_qui_nest_pas_un_prix_ne_filtre_pas(): void
    {
        Property::factory()->published()->create(['title' => 'Villa familiale', 'price_xof' => 60_000_000]);

        foreach (['je cherche une villa pour 10 personnes', 'une villa de 300 m2'] as $message) {
            $reply = $this->demander($message);

            $this->assertNotEmpty(
                $reply['items'],
                "Le message « {$message} » ne doit pas être lu comme un budget.",
            );
        }
    }

    /**
     * Un numéro de téléphone n'est pas un budget.
     */
    public function test_un_numero_de_telephone_ne_filtre_pas(): void
    {
        Property::factory()->published()->create(['title' => 'Villa témoin', 'price_xof' => 60_000_000]);

        $reply = $this->demander('je cherche une villa, mon numéro est 771234567');

        $this->assertNotEmpty($reply['items']);
    }

    /**
     * Le lien « voir toutes les annonces » ne doit pas mener à une page vide.
     *
     * Régression corrigée après revue : la ville était passée en `q`, or les
     * catalogues traitent `q` comme une recherche sur le TITRE. Le visiteur
     * voyait trois résultats pertinents, cliquait, et tombait sur une page vide.
     */
    public function test_le_lien_catalogue_ne_filtre_pas_sur_la_ville(): void
    {
        Property::factory()->published()->create(['title' => 'Villa avec vue']);

        $reply = $this->demander('je cherche une villa à Dakar');

        $lien = collect($reply['actions'])->firstWhere('kind', 'link');

        $this->assertNotNull($lien);
        $this->assertStringNotContainsString('q=', $lien['payload']['url']);
    }

    // =========================================================================
    // FAQ — contenu éditorial du back-office, publié uniquement
    // =========================================================================

    /**
     * Une question sur le service puise dans la FAQ publiée.
     */
    public function test_question_sur_le_service_puise_dans_la_faq(): void
    {
        Faq::factory()->create([
            'question' => 'Comment fonctionne le paiement ?',
            'answer' => 'Nous acceptons Wave, Orange Money et la carte bancaire.',
        ]);

        $reply = $this->demander('comment marche le paiement ?');

        $this->assertSame('consulter_faq', $reply['tool']);
        $this->assertStringContainsString('Wave', $reply['items'][0]['reponse']);
    }

    /**
     * « bien » comme adverbe ne détourne pas vers l'immobilier.
     *
     * Régression corrigée après revue : « bien » figurait parmi les mots-clés
     * immobiliers et la reconnaissance se faisait par sous-chaîne, si bien que
     * « je voudrais bien savoir comment payer » renvoyait des annonces au lieu
     * de la FAQ paiement.
     */
    public function test_bien_comme_adverbe_ne_detourne_pas_vers_immobilier(): void
    {
        Faq::factory()->create([
            'question' => 'Comment fonctionne le paiement ?',
            'answer' => 'Nous acceptons Wave et Orange Money.',
        ]);

        $reply = $this->demander('je voudrais bien savoir comment payer');

        $this->assertSame('consulter_faq', $reply['tool']);
    }

    /**
     * Une entrée de FAQ NON publiée ne fuite pas.
     *
     * Même principe que pour le catalogue : un brouillon en cours de rédaction
     * par l'équipe ne doit pas sortir par l'assistant.
     */
    public function test_une_faq_non_publiee_ne_fuite_pas(): void
    {
        Faq::factory()->create([
            'question' => 'Quels sont les frais de paiement ?',
            'answer' => 'Brouillon interne à ne pas diffuser.',
            'is_published' => false,
        ]);

        $reply = $this->demander('quels sont les frais de paiement ?');

        $this->assertStringNotContainsString(
            'Brouillon interne',
            json_encode($reply, JSON_UNESCAPED_UNICODE),
        );
    }

    // =========================================================================
    // ACCUEIL ET REPLI
    // =========================================================================

    /**
     * Une salutation est accueillie et oriente vers les univers.
     */
    public function test_salutation_oriente_vers_les_univers(): void
    {
        $reply = $this->demander('bonjour');

        $this->assertNull($reply['tool'], 'Une salutation ne consomme aucun outil.');
        $this->assertNotEmpty($reply['actions']);
    }

    /**
     * Une demande incomprise passe la main plutôt que d'inventer.
     *
     * C'est le comportement le plus important pour la confiance : sur un site
     * marchand, une réponse inventée coûte plus cher qu'un « je ne sais pas ».
     */
    public function test_demande_incomprise_passe_la_main(): void
    {
        $reply = $this->demander('quelle est la capitale de la Mongolie');

        $this->assertSame('contacter_support', $reply['tool']);
        $this->assertNotEmpty($reply['actions']);
    }
}
