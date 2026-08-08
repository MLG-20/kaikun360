import { ChangeDetectionStrategy, Component, OnInit, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { CatalogService } from '../../core/api/catalog.service';
import { schemaOrganisation, schemaSite } from '../../core/seo/json-ld';
import { SeoService } from '../../core/seo/seo.service';
import { FavoriteStore } from '../../core/state/favorite-store';
import { CatalogCard, UNIVERSES } from '../../shared/components/catalog/catalog.config';
import { ListingCardComponent } from '../../shared/components/listing-card/listing-card';
import { OrbitHeroComponent } from '../../shared/components/orbit-hero/orbit-hero';
import { CountUpDirective } from '../../shared/directives/count-up.directive';
import { RevealDirective } from '../../shared/directives/reveal.directive';

/**
 * Tuile d'univers de la grille des services.
 *
 * `commands`/`query`/`fragment` décrivent la navigation Angular :
 *   - les univers déjà couverts par le catalogue pointent vers la page de
 *     résultats `/recherche` avec le bon univers présélectionné ;
 *   - ceux dont la page dédiée arrive plus tard (F2.3+) pointent, en attendant,
 *     vers la section correspondante plus bas dans la page d'accueil (`fragment`).
 */
interface UniverseTile {
  /** Nom produit, ex. « Kaikun Immo ». */
  name: string;
  /** Étiquette courte de la catégorie, ex. « Immobilier ». */
  tag: string;
  /** Phrase de présentation. */
  desc: string;
  /** Clé de l'icône (voir le @switch du template). */
  icon: string;
  /** Cible de navigation (routerLink). */
  commands: string[];
  /** Paramètres d'URL éventuels (univers du catalogue). */
  query?: Record<string, string>;
  /** Ancre de section éventuelle (navigation interne à l'accueil). */
  fragment?: string;
}

/** Garantie du protocole de confiance (section navy anti-arnaque). */
interface Guarantee {
  icon: string;
  title: string;
  desc: string;
}

/** Petite carte de service complémentaire (section « Aller plus loin »). */
interface ServiceItem {
  /** Ancre HTML éventuelle (ciblée par une tuile d'univers). */
  anchor?: string;
  icon: string;
  title: string;
  desc: string;
}

/**
 * Page d'accueil publique (F2.2).
 *
 * C'est la vitrine principale de Kaikun 360 : la première page que voit un
 * visiteur. Elle raconte l'offre de haut en bas —
 *   1. un « hero » d'accroche (promesse + preuve de confiance + signature
 *      orbitale animée) posé sur le moteur de recherche global.
 *
 * Les sections suivantes (univers, protocole de confiance, vitrine du catalogue,
 * bandeaux thématiques, simulateur, statistiques) sont ajoutées au fil des
 * sous-phases F2.2.2 → F2.2.4.
 *
 * La page n'a presque pas de logique : elle assemble des composants réutilisables
 * (`app-orbit-hero`) et affiche des contenus statiques de
 * présentation. Les données réelles (catalogue) arriveront via `CatalogService`
 * dans la vitrine (F2.2.3).
 */
@Component({
  selector: 'app-home-page',
  imports: [OrbitHeroComponent, ListingCardComponent, RouterLink, RevealDirective, CountUpDirective],
  templateUrl: './home-page.html',
  styleUrl: './home-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class HomePageComponent implements OnInit {
  private readonly catalog = inject(CatalogService);
  private readonly seo = inject(SeoService);
  /** État partagé des favoris (cœurs sur les biens en vedette). */
  protected readonly favorites = inject(FavoriteStore);

  /** État de chargement de la vitrine : « loading » | « ready » | « failed ». */
  protected readonly featuredState = signal<'loading' | 'ready' | 'failed'>('loading');
  /** Biens vérifiés mis en avant dans la vitrine (données réelles de l'API). */
  protected readonly featured = signal<CatalogCard[]>([]);
  /**
   * Bandeau de confiance affiché sous l'accroche : quelques repères chiffrés qui
   * rassurent immédiatement le visiteur (surtout la diaspora, méfiante des
   * arnaques). Ce sont des repères de présentation, pas des données temps réel.
   */
  protected readonly trust = [
    { value: '14', label: 'régions couvertes' },
    { value: '9', label: 'univers de services' },
    { value: '100 %', label: 'biens vérifiés' },
  ];

  /**
   * Grille des univers de services Kaikun 360. Chaque tuile mène soit au
   * catalogue filtré (univers déjà en ligne), soit à la section dédiée plus bas
   * (univers dont la page complète arrive en F2.3+).
   */
  protected readonly universes: UniverseTile[] = [
    {
      name: 'Kaikun Immo',
      tag: 'Immobilier',
      desc: 'Acheter ou louer un bien vérifié, du studio à la villa.',
      icon: 'home',
      // Page d'univers dédiée (F2.3).
      commands: ['/immobilier'],
    },
    {
      name: 'Kaikun Séjours',
      tag: 'Nuitées',
      desc: 'Séjours courts : maisons, lodges et résidences.',
      icon: 'bed',
      // Page d'univers dédiée (F2.3).
      commands: ['/nuitees'],
    },
    {
      name: 'Kaikun Découverte',
      tag: 'Tourisme',
      desc: 'Expériences, circuits et découvertes au Sénégal.',
      icon: 'compass',
      // Page d'univers dédiée (F2.4).
      commands: ['/tourisme'],
    },
    {
      name: 'Kaikun Mobilité',
      tag: 'Transport & mobilité',
      desc: 'Véhicules avec ou sans chauffeur, navettes et transferts.',
      icon: 'car',
      // Page d'univers dédiée (F2.4). La Mobilité (navettes) y est reliée.
      commands: ['/transport'],
    },
    {
      name: 'Kaikun Chantier',
      tag: 'Construction',
      desc: 'Construire à distance, avec un suivi filmé et daté.',
      icon: 'build',
      // Page de conversion dédiée + simulateur (F2.5).
      commands: ['/construction'],
    },
    {
      name: 'Kaikun Gérance',
      tag: 'Gestion locative',
      desc: 'Confiez la gestion de vos biens et suivez tout à distance.',
      icon: 'key',
      // Page de conversion dédiée (F2.5).
      commands: ['/gestion-locative'],
    },
    {
      name: 'Kaikun Diaspora',
      tag: 'Diaspora',
      desc: 'Un référent unique et un suivi documenté depuis l’étranger.',
      icon: 'globe',
      // Page de conversion dédiée (F2.5).
      commands: ['/diaspora'],
    },
    {
      name: 'Kaikun Groupes',
      tag: 'Cohésion d’équipe',
      desc: 'Séminaires et activités de cohésion clés en main.',
      icon: 'team',
      // Page de conversion dédiée (F2.5).
      commands: ['/team-building'],
    },
    {
      name: 'Kaikun Pro',
      tag: 'Entreprises',
      desc: 'Solutions sur mesure pour les professionnels et institutions.',
      icon: 'pro',
      // Page de conversion dédiée (F2.5).
      commands: ['/pro'],
    },
  ];

  /**
   * Protocole de confiance : les 3 garanties qui structurent le positionnement
   * anti-arnaque de Kaikun 360 (essentiel pour la diaspora, échaudée par les
   * fausses annonces). C'est le cœur du discours de marque.
   */
  protected readonly guarantees: Guarantee[] = [
    {
      icon: 'shield',
      title: 'Vérification documentée',
      desc: 'Titres de propriété, notaire et géomètre contrôlés avant toute mise en ligne.',
    },
    {
      icon: 'camera',
      title: 'Tout est filmé et daté',
      desc: 'Visites, chantiers et livraisons archivés : vous voyez l’avancement réel, pas des promesses.',
    },
    {
      icon: 'ticket',
      title: 'Numéro de suivi unique',
      desc: 'Chaque projet a sa référence : un reporting clair, accessible où que vous soyez.',
    },
  ];

  /**
   * Arguments clés de l'offre diaspora, affichés en liste à côté du bandeau
   * dédié. Ils traduisent le protocole de confiance en bénéfices concrets pour
   * quelqu'un qui pilote un projet depuis l'étranger.
   */
  protected readonly diasporaPoints = [
    'Un référent unique qui coordonne tout sur place',
    'Reporting photo/vidéo horodaté à chaque étape',
    'Paiements sécurisés en FCFA (Wave, Orange Money, virement)',
  ];

  /**
   * Services complémentaires (« aller plus loin ») : cohésion d'équipe, gestion
   * locative et services du quotidien. Certaines cartes portent une ancre
   * ciblée par les tuiles d'univers du haut de page.
   */
  protected readonly services: ServiceItem[] = [
    {
      anchor: 'team-building',
      icon: 'team',
      title: 'Cohésion d’équipe & séminaires',
      desc: 'Organisez la cohésion de vos équipes : lieux, activités et logistique clés en main.',
    },
    {
      icon: 'key',
      title: 'Gestion locative',
      desc: 'Nous gérons vos biens (locataires, loyers, entretien) et vous suivez tout à distance.',
    },
    {
      icon: 'box',
      title: 'Livraison & conciergerie',
      desc: 'Courses, livraisons et services du quotidien pour vous ou vos proches au pays.',
    },
    {
      icon: 'sun',
      title: 'Colonies & séjours groupes',
      desc: 'Séjours encadrés pour enfants et groupes, avec le même niveau de vérification.',
    },
  ];

  /**
   * Étapes du simulateur de construction, présentées en 1-2-3 dans le bandeau
   * dédié. Le simulateur complet (calcul en direct) arrive avec la page
   * Construction en F2.5 ; ici, c'est une invitation à le lancer.
   */
  protected readonly simulatorSteps = [
    { num: '1', label: 'Type de projet', hint: 'Maison, immeuble, extension…' },
    { num: '2', label: 'Surface & standing', hint: 'Nombre de m² et niveau de finition' },
    { num: '3', label: 'Estimation immédiate', hint: 'Une fourchette de budget en FCFA' },
  ];

  /**
   * Chiffres de preuve affichés dans le bandeau de crédibilité (statistiques de
   * présentation, à ajuster quand des données consolidées seront disponibles).
   */
  protected readonly stats = [
    { value: '14', label: 'régions couvertes' },
    { value: '9', label: 'univers de services' },
    { value: '5', label: 'moyens de paiement locaux' },
    { value: '100 %', label: 'projets tracés et archivés' },
  ];

  ngOnInit(): void {
    this.loadFeatured();
    this.referencer();
  }

  /**
   * Données structurées de l'accueil (F9.1) : l'entreprise et le site.
   *
   * ⚠️ **Uniquement ici, et c'est voulu.** `Organization` et `WebSite` décrivent
   * le domaine, pas la page : les répéter sur chaque écran n'apporte rien à
   * Google et alourdit chaque document. C'est aussi l'accueil qui déclare le
   * gabarit de recherche (`/recherche?q=…`), ce qui peut faire apparaître un
   * champ de recherche Kaikun directement dans les résultats Google.
   *
   * ⚠️ Les balises `<meta>`, elles, sont déjà posées par la route
   * (`data.seo` dans `app.routes.ts`) : l'accueil n'a rien à affiner puisque son
   * contenu ne dépend d'aucun identifiant. On n'appelle donc PAS `apply()` —
   * cela ne ferait que réécrire les mêmes valeurs.
   */
  private referencer(): void {
    this.seo.setJsonLd('organisation', schemaOrganisation());
    this.seo.setJsonLd('site', schemaSite());
  }

  /**
   * Charge quelques biens immobiliers publiés pour la vitrine « en vedette ».
   * On réutilise le convertisseur du registre du catalogue (`toCard`) pour un
   * affichage strictement identique à la page de résultats. En cas d'échec
   * réseau (API indisponible), on bascule sur l'état « failed » et la vitrine
   * se replie proprement — le reste de la page reste intact.
   */
  private loadFeatured(): void {
    this.featuredState.set('loading');
    this.catalog.properties({ per_page: 6, sort: 'recent' }).subscribe({
      next: (page) => {
        this.featured.set(page.data.map((item) => UNIVERSES['immobilier'].toCard(item)));
        this.featuredState.set('ready');
      },
      error: () => this.featuredState.set('failed'),
    });
  }
}
