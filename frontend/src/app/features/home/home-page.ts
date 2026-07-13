import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink } from '@angular/router';

import { OrbitHeroComponent } from '../../shared/components/orbit-hero/orbit-hero';
import { SearchEngineComponent } from '../../shared/components/search-engine/search-engine';

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
 * (`app-orbit-hero`, `app-search-engine`) et affiche des contenus statiques de
 * présentation. Les données réelles (catalogue) arriveront via `CatalogService`
 * dans la vitrine (F2.2.3).
 */
@Component({
  selector: 'app-home-page',
  imports: [OrbitHeroComponent, SearchEngineComponent, RouterLink],
  templateUrl: './home-page.html',
  styleUrl: './home-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class HomePageComponent {
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
      commands: ['/recherche'],
      query: { univers: 'immobilier' },
    },
    {
      name: 'Kaikun Stay',
      tag: 'Nuitées',
      desc: 'Séjours courts : maisons, lodges et résidences.',
      icon: 'bed',
      commands: ['/recherche'],
      query: { univers: 'nuitees' },
    },
    {
      name: 'Kaikun Explore',
      tag: 'Tourisme',
      desc: 'Expériences, circuits et découvertes au Sénégal.',
      icon: 'compass',
      commands: ['/recherche'],
      query: { univers: 'tourisme' },
    },
    {
      name: 'Kaikun Mobility',
      tag: 'Transport & mobilité',
      desc: 'Véhicules avec ou sans chauffeur, navettes et transferts.',
      icon: 'car',
      commands: ['/recherche'],
      query: { univers: 'transport' },
    },
    {
      name: 'Kaikun Build',
      tag: 'Construction',
      desc: 'Construire à distance, avec un suivi filmé et daté.',
      icon: 'build',
      commands: ['/'],
      fragment: 'simulateur',
    },
    {
      name: 'Kaikun Manage',
      tag: 'Gestion locative',
      desc: 'Confiez la gestion de vos biens et suivez tout à distance.',
      icon: 'key',
      commands: ['/'],
      fragment: 'services',
    },
    {
      name: 'Kaikun Diaspora',
      tag: 'Diaspora',
      desc: 'Un référent unique et un suivi documenté depuis l’étranger.',
      icon: 'globe',
      commands: ['/'],
      fragment: 'diaspora',
    },
    {
      name: 'Kaikun Team',
      tag: 'Team building',
      desc: 'Séminaires et activités de cohésion clés en main.',
      icon: 'team',
      commands: ['/'],
      fragment: 'team-building',
    },
    {
      name: 'Kaikun Pro',
      tag: 'Entreprises',
      desc: 'Solutions sur mesure pour les professionnels et institutions.',
      icon: 'pro',
      commands: ['/'],
      fragment: 'services',
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
}
