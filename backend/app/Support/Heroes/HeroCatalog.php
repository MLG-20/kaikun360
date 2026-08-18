<?php

namespace App\Support\Heroes;

use App\Models\HeroBanner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Catalogue des bandeaux d'en-tête pilotables au back-office (F12).
 *
 * ## Ce que c'est
 *
 * Chaque grande page publique ouvre sur un bandeau : un surtitre, un titre, une
 * accroche, sur fond de marque. Jusqu'ici ce fond était un dégradé et ces
 * textes vivaient dans les gabarits Angular : changer une photo d'accueil
 * exigeait un redéploiement. Ce catalogue déclare les bandeaux existants et
 * permet à l'équipe de leur donner une image (et, si besoin, d'autres mots)
 * depuis le back-office.
 *
 * ## Deux règles qui ne se ressemblent pas
 *
 * L'héritage ne s'applique PAS de la même façon à l'image et au texte, et c'est
 * délibéré :
 *
 *  - **L'image DESCEND.** Une page sans image propre affiche celle de sa page
 *    parente, et ainsi de suite jusqu'au bandeau `defaut`. L'équipe charge donc
 *    une seule photo d'univers et toutes les pages qui en dépendent en héritent.
 *  - **Le texte ne descend PAS.** Un titre est écrit POUR une page. Faire
 *    hériter le titre d'« Immobilier » à la page de résultats de recherche
 *    afficherait un titre faux — pire que pas de surcharge du tout. Une clé
 *    sans texte propre garde donc le texte écrit dans son gabarit.
 *
 * ## Les clés
 *
 * Une clé est une chaîne pointée (`recherche.nuitees`), mais la parenté n'est
 * **pas** déduite des points : elle est déclarée explicitement ci-dessous. La
 * page de résultats filtrée sur les nuitées doit hériter de l'univers
 * « Nuitées », pas de la page de recherche générique — un lien qu'aucune
 * convention de nommage ne saurait exprimer.
 */
class HeroCatalog
{
    /** Clé du bandeau de dernier recours (racine de tout héritage). */
    public const ROOT = 'defaut';

    private const CACHE_KEY = 'kaikun.heroes';

    /**
     * Bandeaux connus : libellé lisible (back-office), groupe d'affichage,
     * page parente dont l'image est héritée, et note explicative.
     *
     * ⚠️ Ajouter une entrée ici SUFFIT à la rendre pilotable au back-office et
     * consommable côté frontend — aucune migration, aucun écran à retoucher.
     *
     * @var array<string, array{label: string, group: string, parent: ?string, hint: string}>
     */
    public const BANNERS = [
        self::ROOT => [
            'label' => 'Bandeau par défaut',
            'group' => 'general',
            'parent' => null,
            'hint' => 'Image de dernier recours : elle s’affiche partout où aucune image plus précise n’a été chargée.',
        ],

        // --- Les grandes pages d'univers ------------------------------------
        'immobilier' => [
            'label' => 'Immobilier',
            'group' => 'univers',
            'parent' => self::ROOT,
            'hint' => 'Page /immobilier — achat, vente et location de biens.',
        ],
        'nuitees' => [
            'label' => 'Nuitées',
            'group' => 'univers',
            'parent' => self::ROOT,
            'hint' => 'Page /nuitees — séjours et hébergements courte durée.',
        ],
        'tourisme' => [
            'label' => 'Tourisme',
            'group' => 'univers',
            'parent' => self::ROOT,
            'hint' => 'Page /tourisme — circuits, excursions et expériences.',
        ],
        'transport' => [
            'label' => 'Transport',
            'group' => 'univers',
            'parent' => self::ROOT,
            'hint' => 'Page /transport — véhicules à louer, avec ou sans chauffeur.',
        ],
        'mobilite' => [
            'label' => 'Mobilité',
            'group' => 'univers',
            'parent' => self::ROOT,
            'hint' => 'Page /mobilite — navettes, bus, pirogues et trajets organisés.',
        ],
        'construction' => [
            'label' => 'Construction',
            'group' => 'univers',
            'parent' => self::ROOT,
            'hint' => 'Page /construction — simulateur de budget et suivi de chantier.',
        ],
        'gestion-locative' => [
            'label' => 'Gestion locative',
            'group' => 'univers',
            'parent' => self::ROOT,
            'hint' => 'Page /gestion-locative — confier un bien à Kaikun.',
        ],
        'diaspora' => [
            'label' => 'Diaspora',
            'group' => 'univers',
            'parent' => self::ROOT,
            'hint' => 'Page /diaspora — accompagnement à distance depuis l’étranger.',
        ],
        'team-building' => [
            'label' => 'Team building',
            'group' => 'univers',
            'parent' => self::ROOT,
            'hint' => 'Page /team-building — séminaires et sorties d’entreprise.',
        ],
        'pro' => [
            'label' => 'Espace prestataires',
            'group' => 'univers',
            'parent' => self::ROOT,
            'hint' => 'Page /pro — la place de marché des prestataires certifiés.',
        ],

        // --- Sections de la page d'accueil (F16.1) --------------------------
        // Deux sections d'accueil autrement plates (aucune photo réelle avant
        // cette tranche) : Diaspora et l'appel final. Chaque clé est un fond de
        // section, pas un bandeau de page — mais le mécanisme (image seule,
        // hors héritage puisqu'aucune ne partage de parent commun utile) est
        // identique, d'où sa réutilisation plutôt qu'un système dédié de plus.
        'home-diaspora' => [
            'label' => 'Accueil — section Diaspora',
            'group' => 'accueil',
            'parent' => self::ROOT,
            'hint' => 'Fond de la section « Kaikun Diaspora » sur la page d’accueil.',
        ],
        'home-cta' => [
            'label' => 'Accueil — appel final',
            'group' => 'accueil',
            'parent' => self::ROOT,
            'hint' => 'Fond de la section de conversion, en bas de la page d’accueil.',
        ],

        // --- Les pages de service -------------------------------------------
        'contact' => [
            'label' => 'Contact',
            'group' => 'service',
            'parent' => self::ROOT,
            'hint' => 'Page /contact.',
        ],
        'faqs' => [
            'label' => 'Foire aux questions',
            'group' => 'service',
            'parent' => self::ROOT,
            'hint' => 'Page /faqs.',
        ],

        // --- La page de résultats de recherche -------------------------------
        // Une seule page (/recherche) mais cinq visages : l'onglet d'univers
        // actif change le bandeau. Chacun hérite de SON univers, pas du bandeau
        // de recherche générique — c'est tout l'intérêt d'une parenté déclarée.
        'recherche' => [
            'label' => 'Recherche — sans univers',
            'group' => 'recherche',
            'parent' => self::ROOT,
            'hint' => 'Page /recherche quand aucun univers n’est sélectionné.',
        ],
        'recherche.immobilier' => [
            'label' => 'Recherche — Immobilier',
            'group' => 'recherche',
            'parent' => 'immobilier',
            'hint' => 'Résultats filtrés sur l’immobilier. Sans image propre, reprend celle de l’univers Immobilier.',
        ],
        'recherche.nuitees' => [
            'label' => 'Recherche — Nuitées',
            'group' => 'recherche',
            'parent' => 'nuitees',
            'hint' => 'Résultats filtrés sur les nuitées. Sans image propre, reprend celle de l’univers Nuitées.',
        ],
        'recherche.tourisme' => [
            'label' => 'Recherche — Tourisme',
            'group' => 'recherche',
            'parent' => 'tourisme',
            'hint' => 'Résultats filtrés sur le tourisme. Sans image propre, reprend celle de l’univers Tourisme.',
        ],
        'recherche.transport' => [
            'label' => 'Recherche — Transport',
            'group' => 'recherche',
            'parent' => 'transport',
            'hint' => 'Résultats filtrés sur le transport. Sans image propre, reprend celle de l’univers Transport.',
        ],
        'recherche.mobilite' => [
            'label' => 'Recherche — Mobilité',
            'group' => 'recherche',
            'parent' => 'mobilite',
            'hint' => 'Résultats filtrés sur la mobilité. Sans image propre, reprend celle de l’univers Mobilité.',
        ],
    ];

    /**
     * Libellés des groupes d'affichage du back-office.
     *
     * @var array<string, string>
     */
    public const GROUPS = [
        'general' => 'Général',
        'univers' => 'Les univers',
        'accueil' => 'Page d’accueil',
        'service' => 'Pages de service',
        'recherche' => 'Page de résultats',
    ];

    public static function knows(string $key): bool
    {
        return array_key_exists($key, self::BANNERS);
    }

    /**
     * Vue PUBLIQUE de tous les bandeaux : clé → image résolue + textes propres.
     *
     * L'héritage d'image est appliqué **ici**, côté serveur : le frontend se
     * contente de lire l'entrée qui porte sa clé. Faire remonter la chaîne au
     * navigateur l'obligerait à connaître la parenté des pages, c'est-à-dire à
     * dupliquer ce fichier — et à le laisser diverger au premier ajout.
     *
     * Les clés sans aucune surcharge ni image héritée sont **omises** : la page
     * garde alors intégralement son apparence d'origine, et la réponse ne
     * transporte pas dix-neuf objets vides.
     *
     * @return array<string, array{image: ?string, eyebrow: ?string, title: ?string, lead: ?string}>
     */
    public static function published(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $rows = HeroBanner::all()->keyBy('key');

            $resolved = [];

            foreach (array_keys(self::BANNERS) as $key) {
                /** @var HeroBanner|null $own */
                $own = $rows->get($key);

                $entry = [
                    // L'image remonte la chaîne de parenté ; le texte, non.
                    'image' => self::inheritedImage($key, $rows),
                    'eyebrow' => self::clean($own?->eyebrow),
                    'title' => self::clean($own?->title),
                    'lead' => self::clean($own?->lead),
                ];

                // Rien à dire sur cette page : on ne l'envoie pas du tout.
                if ($entry['image'] === null && $entry['eyebrow'] === null
                    && $entry['title'] === null && $entry['lead'] === null) {
                    continue;
                }

                $resolved[$key] = $entry;
            }

            return $resolved;
        });
    }

    /**
     * Vue back-office : TOUTES les clés connues, avec leur libellé, ce qui a été
     * saisi pour elles, et l'image effectivement affichée (héritée ou non).
     *
     * La distinction entre `image` (propre) et `inherited_image` (ce que le
     * visiteur voit réellement) est ce qui rend l'écran compréhensible : sans
     * elle, une page qui affiche déjà la photo de son univers apparaîtrait
     * comme « sans image » et l'équipe rechargerait la même photo partout.
     *
     * @return list<array<string, mixed>>
     */
    public static function forAdmin(): array
    {
        $rows = HeroBanner::all()->keyBy('key');

        $list = [];

        foreach (self::BANNERS as $key => $meta) {
            /** @var HeroBanner|null $own */
            $own = $rows->get($key);
            $ownImage = $own?->imageUrl();

            $list[] = [
                'key' => $key,
                'label' => $meta['label'],
                'group' => $meta['group'],
                'group_label' => self::GROUPS[$meta['group']] ?? $meta['group'],
                'parent' => $meta['parent'],
                'parent_label' => $meta['parent'] ? (self::BANNERS[$meta['parent']]['label'] ?? null) : null,
                'hint' => $meta['hint'],
                'image' => $ownImage,
                // Image réellement affichée par la page (propre ou héritée).
                'inherited_image' => $ownImage ?? self::inheritedImage($key, $rows),
                'eyebrow' => $own?->eyebrow,
                'title' => $own?->title,
                'lead' => $own?->lead,
                'updated_at' => $own?->updated_at?->toIso8601String(),
            ];
        }

        return $list;
    }

    /**
     * Première image trouvée en remontant la chaîne de parenté depuis `$key`.
     *
     * @param  Collection<string, HeroBanner>  $rows
     */
    private static function inheritedImage(string $key, $rows): ?string
    {
        $current = $key;
        // Garde-fou : une parenté mal saisie (cycle) ferait tourner en rond.
        // Le nombre de bandeaux borne le nombre de sauts possibles.
        $hops = count(self::BANNERS) + 1;

        while ($current !== null && $hops-- > 0) {
            $image = $rows->get($current)?->imageUrl();

            if ($image !== null) {
                return $image;
            }

            $current = self::BANNERS[$current]['parent'] ?? null;
        }

        return null;
    }

    /**
     * Normalise un texte saisi : une chaîne vide ou blanche vaut « pas de
     * surcharge », pas « titre vide ». Sans cela, un champ effacé puis
     * enregistré effacerait le titre de la page à l'écran.
     */
    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Invalide le cache public. Appelé à chaque écriture back-office.
     */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
