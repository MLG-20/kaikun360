<?php

namespace Tests\Feature\Transversal;

use App\Models\Page;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Immo\Models\Property;
use App\Modules\Mobility\Models\MobilityService;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Stay\Models\Stay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests F9.2 : le plan du site (`GET /sitemap.xml`).
 *
 * Ces tests sont écrits comme la **vérification d'une promesse faite à un
 * moteur de recherche**, pas comme le contrôle d'un contrôleur. Un plan du site
 * échoue toujours de la même manière : silencieusement. Personne ne voit qu'une
 * annonce n'y figure pas, et personne ne voit qu'un brouillon y figure — jusqu'à
 * ce qu'il apparaisse dans Google. D'où deux familles d'assertions systématiques
 * pour chaque univers : **le publié est présent** ET **le non-publié est
 * absent**.
 *
 * ⚠️ La seconde famille est la plus importante des deux. Un oubli de la
 * première coûte du référencement ; un oubli de la seconde **publie sur
 * l'internet des annonces qu'un agent n'a pas encore validées** — le plan du
 * site contournerait alors tout le circuit de modération du back-office.
 */
class SitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Le plan du site liste des URL du SITE, jamais de l'API : on fige le
        // domaine attendu pour que les assertions portent sur la bonne origine.
        config()->set('branding.frontend', 'https://site.test');
    }

    public function test_le_plan_du_site_est_un_document_xml_public(): void
    {
        // Aucune authentification : un robot n'a pas de compte.
        $reponse = $this->get('/sitemap.xml');

        $reponse->assertOk();
        $reponse->assertHeader('Content-Type', 'application/xml; charset=utf-8');

        $xml = $reponse->getContent();

        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);

        // ⚠️ Le document doit être analysable : une seule esperluette non
        // échappée le rend invalide EN ENTIER, et Google rejette alors le plan
        // complet sans dire quelle ligne fautait.
        $this->assertNotFalse(
            simplexml_load_string($xml),
            'Le plan du site n\'est pas un XML valide : Google le rejetterait en bloc.',
        );
    }

    public function test_il_annonce_les_pages_publiques_du_site_et_jamais_les_espaces_connectes(): void
    {
        $xml = $this->get('/sitemap.xml')->getContent();

        foreach (['/', '/immobilier', '/nuitees', '/tourisme', '/transport', '/mobilite', '/construction', '/gestion-locative', '/diaspora', '/team-building', '/pro', '/faqs', '/contact'] as $chemin) {
            $this->assertStringContainsString(
                "<loc>https://site.test{$chemin}</loc>",
                $xml,
                "La page publique {$chemin} manque au plan du site.",
            );
        }

        // ⚠️ Les espaces connectés sont `noindex` côté frontend : les annoncer
        // ici serait se contredire, et inviterait les robots à les parcourir.
        foreach (['/mon-espace', '/espace-proprietaire', '/espace-prestataire', '/espace-entreprise', '/back-office', '/auth', '/devis', '/paiement'] as $prive) {
            $this->assertStringNotContainsString(
                "https://site.test{$prive}",
                $xml,
                "Le plan du site annonce l'espace privé {$prive}.",
            );
        }

        // `/recherche` est délibérément hors index (mêmes offres que les
        // catalogues sous un nombre illimité d'URL filtrées).
        $this->assertStringNotContainsString('https://site.test/recherche', $xml);
    }

    public function test_il_annonce_les_biens_publies_et_tait_les_autres(): void
    {
        $publie = Property::factory()->published()->create();
        $enAttente = Property::factory()->create();

        $xml = $this->get('/sitemap.xml')->getContent();

        $this->assertStringContainsString("<loc>https://site.test/immobilier/{$publie->id}</loc>", $xml);
        $this->assertStringNotContainsString("/immobilier/{$enAttente->id}<", $xml);
    }

    public function test_il_annonce_les_nuitees_reservables_et_tait_les_desactivees(): void
    {
        $active = Stay::factory()->create();
        $desactivee = Stay::factory()->inactive()->create();
        // ⚠️ Une nuitée active dont le BIEN n'est pas publié n'est pas
        // réservable : le catalogue public ne la sert pas, le plan non plus.
        $bienNonPublie = Stay::factory()->for(Property::factory())->create();

        $xml = $this->get('/sitemap.xml')->getContent();

        $this->assertStringContainsString("<loc>https://site.test/nuitees/{$active->id}</loc>", $xml);
        $this->assertStringNotContainsString("/nuitees/{$desactivee->id}<", $xml);
        $this->assertStringNotContainsString("/nuitees/{$bienNonPublie->id}<", $xml);
    }

    public function test_il_annonce_les_circuits_et_vehicules_publies_et_tait_les_autres(): void
    {
        $circuit = TourismExperience::factory()->published()->create();
        $circuitEnAttente = TourismExperience::factory()->create();
        $vehicule = Vehicle::factory()->published()->create();
        $vehiculeEnAttente = Vehicle::factory()->create();

        $xml = $this->get('/sitemap.xml')->getContent();

        $this->assertStringContainsString("<loc>https://site.test/tourisme/{$circuit->id}</loc>", $xml);
        $this->assertStringNotContainsString("/tourisme/{$circuitEnAttente->id}<", $xml);
        $this->assertStringContainsString("<loc>https://site.test/transport/{$vehicule->id}</loc>", $xml);
        $this->assertStringNotContainsString("/transport/{$vehiculeEnAttente->id}<", $xml);
    }

    /**
     * ⚠️ Le cas qui distingue la mobilité de tous les autres univers.
     *
     * Un départ est **daté**. Publié mais passé, il n'est plus au catalogue et
     * son paiement est refusé depuis F8.23.a — l'annoncer à Google ferait
     * indexer une page qui ne mène à rien, et le stock de départs périmés ne
     * fait que croître avec le temps.
     */
    public function test_il_annonce_les_departs_a_venir_et_tait_ceux_deja_partis(): void
    {
        $aVenir = MobilityService::factory()->published()->create([
            'departure_at' => now()->addWeek(),
        ]);
        $passe = MobilityService::factory()->published()->create([
            'departure_at' => now()->subWeek(),
        ]);
        $enAttente = MobilityService::factory()->create([
            'departure_at' => now()->addWeek(),
        ]);

        $xml = $this->get('/sitemap.xml')->getContent();

        $this->assertStringContainsString("<loc>https://site.test/mobilite/{$aVenir->id}</loc>", $xml);
        $this->assertStringNotContainsString("/mobilite/{$passe->id}<", $xml);
        $this->assertStringNotContainsString("/mobilite/{$enAttente->id}<", $xml);
    }

    public function test_il_annonce_les_pages_editoriales_publiees_par_leur_slug(): void
    {
        $publiee = Page::factory()->create(['slug' => 'cgv']);
        $brouillon = Page::factory()->draft()->create(['slug' => 'note-interne']);

        $xml = $this->get('/sitemap.xml')->getContent();

        $this->assertStringContainsString("<loc>https://site.test/pages/{$publiee->slug}</loc>", $xml);
        $this->assertStringNotContainsString('/pages/note-interne', $xml);
    }

    /**
     * La date de dernière modification aide un moteur à ne réexplorer que ce
     * qui a bougé. Elle doit être au format Atom, sinon elle est ignorée en
     * silence — le plan reste valide, mais perd son intérêt principal.
     */
    public function test_chaque_fiche_porte_sa_date_de_derniere_modification(): void
    {
        $bien = Property::factory()->published()->create();

        $xml = $this->get('/sitemap.xml')->getContent();

        $this->assertStringContainsString(
            "<loc>https://site.test/immobilier/{$bien->id}</loc><lastmod>{$bien->updated_at->toAtomString()}</lastmod>",
            $xml,
        );
    }
}
