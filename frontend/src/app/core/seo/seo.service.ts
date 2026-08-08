import { DOCUMENT } from '@angular/common';
import { Injectable, Injector, inject } from '@angular/core';
import { Meta, MetaDefinition, Title } from '@angular/platform-browser';
import { Router } from '@angular/router';

import { environment } from '../../../environments/environment';
import { SeoTags } from './seo.model';

/** Suffixe de marque ajouté aux titres qui ne le portent pas déjà. */
const MARQUE = 'Kaikun 360';

/** Image de partage par défaut (1200×630), servie depuis `public/`. */
const IMAGE_PAR_DEFAUT = '/og-image.png';

/**
 * Identifiant `data-seo` posé sur les balises que ce service gère.
 *
 * ⚠️ Il ne sert pas à décorer : il sert à **retrouver et supprimer** nos
 * balises sans toucher à celles écrites en dur dans `index.html`
 * (`charset`, `viewport`, `theme-color`, l'icône Apple…). Sans marqueur, le
 * ménage entre deux navigations les emporterait aussi.
 */
const MARQUEUR = 'data-seo';

/**
 * Balises de référencement de la page courante (F9.1).
 *
 * ## Pourquoi ce service existe
 *
 * Le rendu serveur (SSR) était configuré depuis F2.9 et 122 titres de route
 * étaient posés, mais **aucune page n'avait de description, d'URL canonique ni
 * de balise OpenGraph**. Conséquences concrètes, pas théoriques : un lien
 * partagé sur WhatsApp — le canal de conversion principal du projet —
 * s'affichait sans titre ni image ; et Google, faute de description, en
 * inventait une à partir du premier texte trouvé (souvent le menu).
 *
 * ## Comment il est appelé
 *
 * Deux temps, jamais un seul :
 *
 *   1. `SeoTitleStrategy` applique le repli déclaré par la route **à chaque
 *      navigation**, avant tout appel HTTP ;
 *   2. une page de fiche appelle `apply()` **une fois ses données reçues**,
 *      avec le vrai titre et la vraie photo.
 *
 * Une page qui n'appelle jamais `apply()` reste donc correctement décrite.
 *
 * ## Ce qui rend ce service sûr au rendu serveur
 *
 * `Meta`, `Title` et `DOCUMENT` d'Angular écrivent dans le document **rendu**,
 * pas dans le `document` du navigateur : ils fonctionnent identiquement sur le
 * serveur Node. C'est tout l'intérêt — les balises doivent être présentes dans
 * le HTML **envoyé**, puisqu'un robot de réseau social n'exécute aucun
 * JavaScript. ⚠️ Ne jamais y introduire `window` ou `navigator` : cela romprait
 * le rendu serveur, et ferait de surcroît diverger le DOM serveur du DOM client
 * (l'hydratation échouerait — le piège exact rencontré en F8.7 avec Google
 * Identity).
 */
@Injectable({ providedIn: 'root' })
export class SeoService {
  private readonly document = inject(DOCUMENT);
  private readonly meta = inject(Meta);
  private readonly title = inject(Title);

  /**
   * ⚠️ **Le `Router` est réclamé à l'usage, jamais au constructeur** — et ce
   * n'est pas une préférence de style, c'est ce qui empêche le démarrage de
   * planter avec un `NG0200` (dépendance circulaire) :
   *
   *   `Router` → `TitleStrategy` → `SeoTitleStrategy` → `SeoService` → `Router`
   *
   * Le routeur exige sa stratégie de titre pour se construire ; si le service
   * qu'elle utilise réclame le routeur à son tour, la boucle est fermée avant
   * qu'aucun des deux n'existe. Le symptôme est brutal et trompeur : au build,
   * l'extraction des routes échoue avec « An error occurred while extracting
   * routes » — aucun rapport apparent avec le référencement.
   *
   * Résoudre à la demande casse le cycle sans détour : quand une page navigue,
   * le routeur est construit depuis longtemps.
   */
  private readonly injector = inject(Injector);

  /** Racine publique du site, sans barre oblique finale. */
  private readonly site = environment.siteUrl.replace(/\/+$/, '');

  /**
   * Écrit **tout** le jeu de balises de la page courante.
   *
   * Réécriture complète et non fusion : voir la note de `SeoTags`.
   */
  apply(tags: SeoTags): void {
    const titre = this.avecMarque(tags.title);
    const description = this.tronquer(tags.description);
    const url = this.absolu(tags.canonicalPath ?? this.cheminCourant());
    const image = this.absolu(tags.image ?? IMAGE_PAR_DEFAUT);
    const type = tags.type ?? 'website';
    // `follow` même hors index : les liens d'une page non indexée mènent quand
    // même le robot vers le catalogue, qui, lui, doit être exploré.
    const robots = tags.index === false ? 'noindex, follow' : 'index, follow';

    this.title.setTitle(titre);
    this.jeuDeBalises({
      description,
      robots,
      'og:type': type,
      'og:site_name': MARQUE,
      'og:locale': 'fr_SN',
      'og:title': titre,
      'og:description': description,
      'og:url': url,
      'og:image': image,
      // Twitter/X lit `summary_large_image` pour afficher une vraie vignette ;
      // sans cette balise il retombe sur une miniature carrée illisible.
      'twitter:card': 'summary_large_image',
      'twitter:title': titre,
      'twitter:description': description,
      'twitter:image': image,
    });
    this.canonique(url);
  }

  /**
   * Pose un bloc de **données structurées** JSON-LD (schema.org).
   *
   * `cle` identifie le bloc : réappliquer la même clé remplace le bloc
   * précédent. Une page peut donc en poser plusieurs (par exemple le fil
   * d'Ariane et l'offre) sans se marcher dessus.
   *
   * ⚠️ Le JSON est injecté via `textContent`, jamais `innerHTML` : une
   * description de bien saisie par un propriétaire peut contenir n'importe
   * quoi, et `JSON.stringify` n'échappe pas `</script>`. D'où aussi le
   * remplacement du chevron fermant ci-dessous — c'est la seule séquence
   * capable de refermer la balise depuis l'intérieur.
   */
  setJsonLd(cle: string, schema: object): void {
    const existant = this.document.head.querySelector<HTMLScriptElement>(
      `script[type="application/ld+json"][${MARQUEUR}="${cle}"]`,
    );
    const script = existant ?? this.document.createElement('script');
    if (!existant) {
      script.type = 'application/ld+json';
      script.setAttribute(MARQUEUR, cle);
      this.document.head.appendChild(script);
    }
    script.textContent = JSON.stringify(schema).replace(/</g, '\\u003c');
  }

  /**
   * Retire tous les blocs JSON-LD posés.
   *
   * Appelé à chaque navigation par `SeoTitleStrategy` : sans ce ménage, la
   * fiche d'un bien laisserait son schéma `Offer` derrière elle, et la page
   * Contact décrirait au robot un appartement à Ngor.
   */
  clearJsonLd(): void {
    this.document.head
      .querySelectorAll(`script[type="application/ld+json"][${MARQUEUR}]`)
      .forEach((noeud) => noeud.remove());
  }

  // ---------------------------------------------------------------- internes

  /**
   * Réécrit chaque balise du jeu.
   *
   * ⚠️ Aucun ménage n'est nécessaire ici, et c'est une propriété à préserver :
   * `apply()` passe **toujours exactement les mêmes clés**. Le jour où une
   * balise deviendrait conditionnelle, il faudrait supprimer sa version
   * précédente — sinon elle survivrait à la navigation suivante.
   */
  private jeuDeBalises(valeurs: Record<string, string>): void {
    for (const [nom, contenu] of Object.entries(valeurs)) {
      // Les balises OpenGraph s'adressent par `property`, les autres par
      // `name` : c'est la convention que lisent Facebook et WhatsApp, et une
      // balise `og:*` posée en `name` est purement et simplement ignorée.
      const ouvert = nom.startsWith('og:');
      const selecteur = ouvert ? `property='${nom}'` : `name='${nom}'`;
      const attributs: MetaDefinition = ouvert
        ? { property: nom, content: contenu }
        : { name: nom, content: contenu };
      const balise = this.meta.updateTag(attributs, selecteur);
      balise?.setAttribute(MARQUEUR, '');
    }
  }

  /** Pose (ou déplace) le `<link rel="canonical">`. */
  private canonique(url: string): void {
    const existant = this.document.head.querySelector<HTMLLinkElement>('link[rel="canonical"]');
    const lien = existant ?? this.document.createElement('link');
    if (!existant) {
      lien.setAttribute('rel', 'canonical');
      this.document.head.appendChild(lien);
    }
    lien.setAttribute('href', url);
  }

  /**
   * Chemin de l'URL courante, **paramètres de requête retirés**.
   *
   * ⚠️ C'est volontaire et c'est le cœur de l'URL canonique : sans cela,
   * `/immobilier?page=2&type=villa&region=3` et ses dizaines de combinaisons
   * seraient autant de pages distinctes aux yeux de Google, qui se disputent le
   * même contenu. Une page qui a besoin d'un canonique avec paramètres passe
   * `canonicalPath` explicitement.
   */
  private cheminCourant(): string {
    return this.injector.get(Router).url.split(/[?#]/)[0] || '/';
  }

  /** Rend une URL absolue ; laisse intactes celles qui le sont déjà (photos de l'API). */
  private absolu(url: string): string {
    if (/^https?:\/\//i.test(url)) {
      return url;
    }
    return `${this.site}${url.startsWith('/') ? '' : '/'}${url}`;
  }

  /** Ajoute le suffixe de marque, sauf si le titre le porte déjà. */
  private avecMarque(titre: string): string {
    const propre = titre.trim();
    if (!propre) {
      return MARQUE;
    }
    return propre.includes(MARQUE) ? propre : `${propre} — ${MARQUE}`;
  }

  /**
   * Ramène une description à ~160 caractères, coupée sur un mot.
   *
   * Utile surtout pour les fiches, dont la description vient d'un texte libre
   * saisi par un propriétaire : sans coupe, Google tronque au milieu d'un mot.
   */
  private tronquer(texte: string): string {
    const propre = texte.replace(/\s+/g, ' ').trim();
    if (propre.length <= 160) {
      return propre;
    }
    const coupe = propre.slice(0, 157);
    const espace = coupe.lastIndexOf(' ');
    return `${(espace > 100 ? coupe.slice(0, espace) : coupe).trimEnd()}…`;
  }
}
