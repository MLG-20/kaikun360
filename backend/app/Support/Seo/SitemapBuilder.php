<?php

namespace App\Support\Seo;

use App\Models\Page;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Immo\Models\Property;
use App\Modules\Mobility\Models\MobilityService;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Stay\Models\Stay;
use Illuminate\Support\Carbon;

/**
 * Construction du plan du site (`sitemap.xml`) — F9.2.
 *
 * ## Pourquoi le plan du site est produit ICI et pas côté Angular
 *
 * Le plan du site doit énumérer **chaque fiche publiée** : biens, nuitées,
 * circuits, véhicules, départs, pages éditoriales. Ce sont des données, et les
 * données vivent dans cette base. Le construire côté frontend obligerait le
 * serveur de rendu à repaginer quatre catalogues par appel — plus lent, plus
 * fragile, et intestable. Ici, c'est une requête par table, et la suite de
 * tests peut le vérifier.
 *
 * ⚠️ **Les URL produites sont celles du SITE, pas de l'API** : elles partent de
 * `config('branding.frontend')` (`FRONTEND_URL`), le même réglage que les liens
 * des e-mails depuis F8.8. Utiliser `APP_URL` publierait dans Google des liens
 * vers l'API — qui répondrait du JSON à des visiteurs.
 *
 * ⚠️ **Un plan du site n'est pas un contrôle d'accès** : tout ce qui est listé
 * ici est appelé à être visité par des robots. On n'y met donc QUE ce que les
 * endpoints publics servent déjà, avec exactement les mêmes filtres de
 * publication (`published()`, `bookable()`, `aVenir()`). Le jour où un filtre
 * public change, celui-ci doit changer avec — sinon le plan du site annonce des
 * pages qui répondent 404.
 */
class SitemapBuilder
{
    /**
     * Pages fixes du site, avec leur priorité relative.
     *
     * ⚠️ Cette liste double `app.routes.ts` côté Angular, et c'est inévitable :
     * les deux applications sont séparées. **N'y mettre que des pages
     * publiques** — les espaces connectés et le back-office n'ont rien à y
     * faire (ils sont `noindex` côté frontend, ce serait se contredire). Et ne
     * PAS y remettre `/recherche` : ses résultats sont volontairement hors
     * index (contenu dupliqué des catalogues).
     *
     * @var array<string, float>
     */
    private const PAGES_FIXES = [
        '/' => 1.0,
        '/immobilier' => 0.9,
        '/nuitees' => 0.9,
        '/tourisme' => 0.9,
        '/transport' => 0.8,
        '/mobilite' => 0.8,
        '/construction' => 0.8,
        '/gestion-locative' => 0.8,
        '/diaspora' => 0.8,
        '/team-building' => 0.7,
        '/pro' => 0.7,
        '/pro/inscription' => 0.6,
        '/deposer-un-bien' => 0.6,
        '/faqs' => 0.5,
        '/contact' => 0.5,
    ];

    /**
     * Rend le document XML complet.
     */
    public function render(): string
    {
        $lignes = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $lignes[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($this->entrees() as $entree) {
            $lignes[] = $this->entree($entree['loc'], $entree['lastmod'] ?? null, $entree['priority']);
        }

        $lignes[] = '</urlset>';

        return implode("\n", $lignes)."\n";
    }

    /**
     * Toutes les entrées du plan, pages fixes puis fiches.
     *
     * Exposée séparément du rendu pour que les tests puissent raisonner sur des
     * URL plutôt que sur du XML.
     *
     * @return list<array{loc: string, lastmod: ?Carbon, priority: float}>
     */
    public function entrees(): array
    {
        $entrees = [];

        foreach (self::PAGES_FIXES as $chemin => $priorite) {
            $entrees[] = ['loc' => $this->url($chemin), 'lastmod' => null, 'priority' => $priorite];
        }

        // ⚠️ `select` explicite et pas de modèles complets : un catalogue de
        // plusieurs milliers de biens hydraté en objets Eloquent ferait de cette
        // route la plus coûteuse du site — pour un document que seuls des robots
        // lisent.
        Property::query()
            ->published()
            ->select(['id', 'updated_at'])
            ->orderBy('id')
            ->each(function (Property $bien) use (&$entrees) {
                $entrees[] = [
                    'loc' => $this->url("/immobilier/{$bien->id}"),
                    'lastmod' => $bien->updated_at,
                    'priority' => 0.7,
                ];
            });

        Stay::query()
            ->bookable()
            ->select(['id', 'updated_at'])
            ->orderBy('id')
            ->each(function (Stay $nuitee) use (&$entrees) {
                $entrees[] = [
                    'loc' => $this->url("/nuitees/{$nuitee->id}"),
                    'lastmod' => $nuitee->updated_at,
                    'priority' => 0.7,
                ];
            });

        TourismExperience::query()
            ->published()
            ->select(['id', 'updated_at'])
            ->orderBy('id')
            ->each(function (TourismExperience $circuit) use (&$entrees) {
                $entrees[] = [
                    'loc' => $this->url("/tourisme/{$circuit->id}"),
                    'lastmod' => $circuit->updated_at,
                    'priority' => 0.7,
                ];
            });

        Vehicle::query()
            ->published()
            ->select(['id', 'updated_at'])
            ->orderBy('id')
            ->each(function (Vehicle $vehicule) use (&$entrees) {
                $entrees[] = [
                    'loc' => $this->url("/transport/{$vehicule->id}"),
                    'lastmod' => $vehicule->updated_at,
                    'priority' => 0.6,
                ];
            });

        // ⚠️ `aVenir()` en plus de `published()` : un départ passé n'est plus au
        // catalogue et son paiement est refusé depuis F8.23.a. L'annoncer à
        // Google ferait indexer des pages qui ne mènent nulle part — et le
        // nombre de départs périmés ne fait que croître.
        MobilityService::query()
            ->published()
            ->aVenir()
            ->select(['id', 'updated_at'])
            ->orderBy('id')
            ->each(function (MobilityService $depart) use (&$entrees) {
                $entrees[] = [
                    'loc' => $this->url("/mobilite/{$depart->id}"),
                    'lastmod' => $depart->updated_at,
                    'priority' => 0.5,
                ];
            });

        Page::query()
            ->published()
            ->select(['slug', 'updated_at'])
            ->orderBy('slug')
            ->each(function (Page $page) use (&$entrees) {
                $entrees[] = [
                    'loc' => $this->url("/pages/{$page->slug}"),
                    'lastmod' => $page->updated_at,
                    'priority' => 0.4,
                ];
            });

        return $entrees;
    }

    /** Une entrée `<url>` du document. */
    private function entree(string $loc, ?Carbon $lastmod, float $priority): string
    {
        // ⚠️ `htmlspecialchars` obligatoire : un slug de page ou un identifiant
        // ne devrait jamais contenir `&`, mais un seul esperluette non échappée
        // rend le document XML invalide **en entier** — Google rejette alors le
        // plan du site complet, sans indiquer quelle ligne fautait.
        $xml = '  <url><loc>'.htmlspecialchars($loc, ENT_XML1).'</loc>';

        if ($lastmod) {
            $xml .= '<lastmod>'.$lastmod->toAtomString().'</lastmod>';
        }

        $xml .= '<priority>'.number_format($priority, 1, '.', '').'</priority></url>';

        return $xml;
    }

    /** Compose une URL absolue du SITE (jamais de l'API). */
    private function url(string $chemin): string
    {
        return rtrim((string) config('branding.frontend'), '/').$chemin;
    }
}
