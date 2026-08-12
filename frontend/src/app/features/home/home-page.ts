import { isPlatformBrowser } from '@angular/common';
import {
  ChangeDetectionStrategy,
  Component,
  OnDestroy,
  OnInit,
  PLATFORM_ID,
  inject,
  signal,
} from '@angular/core';
import { RouterLink } from '@angular/router';
import { Observable, map } from 'rxjs';

import { CatalogService } from '../../core/api/catalog.service';
import { schemaOrganisation, schemaSite } from '../../core/seo/json-ld';
import { SeoService } from '../../core/seo/seo.service';
import { FavoriteStore } from '../../core/state/favorite-store';
import {
  CatalogCard,
  FilterValues,
  UNIVERSES,
  Universe,
} from '../../shared/components/catalog/catalog.config';
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
 * Cadence du tour de rôle de la vitrine.
 *
 * Sept secondes : le temps de parcourir six cartes des yeux sans se sentir
 * pressé. Plus court, on ne lit rien ; plus long, on ne soupçonne pas qu'il y a
 * autre chose à voir et la rotation ne sert à rien.
 */
const ROTATION_MS = 7000;

/**
 * Durée au-delà de laquelle une pause au survol est tenue pour perdue (une
 * minute). Personne ne laisse sa souris immobile sur une carte aussi longtemps
 * en la lisant ; en revanche un `mouseleave` manqué, lui, dure toujours.
 */
const PAUSE_MAX_MS = 60000;

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
export class HomePageComponent implements OnInit, OnDestroy {
  private readonly catalog = inject(CatalogService);
  private readonly seo = inject(SeoService);
  /** État partagé des favoris (cœurs sur les biens en vedette). */
  protected readonly favorites = inject(FavoriteStore);

  private readonly estNavigateur = isPlatformBrowser(inject(PLATFORM_ID));

  /** État de chargement de la vitrine : « loading » | « ready » | « failed ». */
  protected readonly featuredState = signal<'loading' | 'ready' | 'failed'>('loading');
  /** Biens vérifiés mis en avant dans la vitrine (données réelles de l'API). */
  protected readonly featured = signal<CatalogCard[]>([]);

  // ---------------------------------------------------------------------------
  // Vitrine tournante (F13.5)
  //
  // La vitrine ne montrait que l'immobilier : les quatre autres univers — dont
  // le tourisme et le transport, qui portent l'essentiel de la promesse — ne se
  // voyaient nulle part sur la page d'accueil. Elle passe donc en revue les cinq
  // univers, six cartes chacun.
  // ---------------------------------------------------------------------------

  /** Ordre de passage des univers dans la vitrine. */
  protected readonly universKeys = Object.keys(UNIVERSES) as Universe[];

  /** Univers actuellement exposé. */
  protected readonly universCourant = signal<Universe>('immobilier');

  /**
   * Titre de section propre à chaque univers.
   *
   * ⚠️ Un titre unique ne pouvait pas convenir : « Des biens vérifiés, prêts à
   * visiter » est faux au-dessus d'une grille de véhicules. La phrase suit donc
   * ce qui est montré, sinon la vitrine annonce autre chose que son contenu.
   */
  protected readonly universTitres: Record<Universe, string> = {
    immobilier: 'Des biens vérifiés, prêts à visiter',
    nuitees: 'Des séjours vérifiés, prêts à réserver',
    transport: 'Des véhicules vérifiés, avec ou sans chauffeur',
    tourisme: 'Des circuits vérifiés, prêts à partir',
    mobilite: 'Des départs programmés, aux horaires annoncés',
  };

  /** Libellé d'un univers (repris du registre du catalogue). */
  protected libelle(u: Universe): string {
    return UNIVERSES[u].label;
  }

  /**
   * Cartes déjà chargées, par univers.
   *
   * ⚠️ Sans ce cache, un tour complet redemanderait les cinq univers à chaque
   * boucle : la vitrine tournant toute seule, l'accueil bombarderait l'API tant
   * que l'onglet reste ouvert. Ici chaque univers n'est chargé qu'une fois, et
   * les tours suivants sont instantanés — c'est aussi ce qui rend le passage
   * fluide plutôt que saccadé par un temps de réseau.
   */
  private readonly cartesParUnivers = new Map<Universe, CatalogCard[]>();

  /**
   * Univers sans aucune annonce publiée : ils sont **retirés du tour**.
   *
   * ⚠️ Indispensable sur une plateforme qui démarre — c'est le cas normal ici,
   * pas un cas limite. Sans cette exclusion, la vitrine s'arrêterait
   * régulièrement sur « Le catalogue s'enrichit », donnant à un visiteur (ou à
   * un client à qui l'on montre le site) l'impression d'un catalogue vide.
   */
  private readonly universVides = new Set<Universe>();

  /** Minuteur du tour de rôle (navigateur uniquement). */
  private minuteur: ReturnType<typeof setInterval> | null = null;

  /** Le tour est suspendu tant que la souris est sur la vitrine. */
  private survolee = false;

  /**
   * Suspendu aussi tant qu'un élément de la vitrine a le focus CLAVIER.
   *
   * Deux drapeaux distincts et non un seul : la souris et le clavier entrent et
   * sortent indépendamment, et les confondre laissait la vitrine figée (voir
   * `focusEntrant`).
   */
  private focusClavier = false;

  /**
   * Instant où la pause au survol a commencé.
   *
   * ⚠️ **Garde-fou, et il a sa raison d'être** : cette vitrine s'est déjà figée
   * deux fois pour des pauses qui ne se terminaient jamais. Un `mouseleave` peut
   * manquer à l'appel — élément retiré du DOM sous le curseur, défilement qui
   * déplace la carte sans événement de souris — et le tour s'arrête alors pour
   * de bon, sans que rien à l'écran ne l'explique. Au-delà de PAUSE_MAX_MS, on
   * considère la pause comme perdue et on repart.
   */
  private pauseDepuis: number | null = null;
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
    this.montrer(this.universCourant());
    this.demarrerLeTour();
  }

  /**
   * Affiche un univers dans la vitrine : depuis le cache si possible, sinon en
   * le chargeant. Sert autant au tour de rôle qu'au clic sur une pastille.
   */
  private montrer(u: Universe, prochargerLeSuivant = true): void {
    const enCache = this.cartesParUnivers.get(u);
    if (enCache) {
      this.featured.set(enCache);
      this.featuredState.set('ready');
      if (prochargerLeSuivant) this.precharger(this.universSuivant(u));
      return;
    }

    this.featuredState.set('loading');
    this.charger(u).subscribe({
      next: (cartes) => {
        this.cartesParUnivers.set(u, cartes);

        // ⚠️ L'univers a pu changer pendant la requête (tour de rôle ou clic) :
        // sans cette garde, une réponse tardive écraserait la grille affichée
        // par celle d'un univers qu'on ne regarde plus. Les cartes sont tout de
        // même mises en cache ci-dessus — le travail n'est pas perdu.
        if (this.universCourant() !== u) return;

        if (!cartes.length) {
          this.sauterUniversVide(u);
          return;
        }

        this.featured.set(cartes);
        this.featuredState.set('ready');
        if (prochargerLeSuivant) this.precharger(this.universSuivant(u));
      },
      error: () => {
        if (this.universCourant() === u) this.featuredState.set('failed');
      },
    });
  }

  /** Charge six annonces d'un univers et les convertit en cartes. */
  private charger(u: Universe): Observable<CatalogCard[]> {
    const config = UNIVERSES[u];
    const filtres: FilterValues = { per_page: 6 };
    // ⚠️ Le tri n'est pas offert par tous les univers : l'envoyer là où le
    // backend ne l'attend pas expose à un refus de validation.
    if (config.hasSort) filtres['sort'] = 'recent';

    return config
      .fetch(this.catalog, filtres)
      .pipe(map((page) => page.data.map((item) => config.toCard(item))));
  }

  /**
   * Charge en avance l'univers suivant, sans rien afficher : au moment du
   * basculement, la grille est déjà là. C'est ce qui fait la fluidité — un
   * chargement visible à chaque tour donnerait une vitrine qui clignote.
   */
  private precharger(u: Universe): void {
    if (!this.estNavigateur || this.cartesParUnivers.has(u)) return;

    this.charger(u).subscribe({
      next: (cartes) => {
        this.cartesParUnivers.set(u, cartes);
        if (!cartes.length) this.universVides.add(u);
      },
      // Un préchargement raté ne doit rien casser : l'univers sera simplement
      // rechargé (et son échec traité) quand son tour viendra vraiment.
      error: () => undefined,
    });
  }

  /** Retire un univers vide du tour et passe immédiatement au suivant. */
  private sauterUniversVide(u: Universe): void {
    this.universVides.add(u);
    const suivant = this.universSuivant(u);

    // Tous les univers sont vides : on assume la grille vide plutôt que de
    // boucler à l'infini sur des chargements sans contenu.
    if (suivant === u) {
      this.featured.set([]);
      this.featuredState.set('ready');
      return;
    }

    this.basculerVers(suivant);
  }

  /**
   * Bascule la vitrine sur un univers.
   *
   * ⚠️ **Le signal est posé AVANT l'affichage, et l'ordre n'est pas cosmétique** :
   * `montrer()` vérifie, au retour de la requête, que l'univers demandé est
   * toujours celui qu'on regarde. Basculer dans l'autre sens ferait échouer
   * cette garde à tous les coups — la vitrine chargerait les cartes puis les
   * jetterait, et resterait éternellement sur le premier univers.
   */
  private basculerVers(u: Universe): void {
    this.universCourant.set(u);
    this.montrer(u);
  }

  /** Univers suivant dans l'ordre, en sautant ceux qu'on sait vides. */
  private universSuivant(depuis: Universe): Universe {
    const ordre = this.universKeys;
    const depart = ordre.indexOf(depuis);

    for (let pas = 1; pas <= ordre.length; pas++) {
      const candidat = ordre[(depart + pas) % ordre.length];
      if (!this.universVides.has(candidat)) return candidat;
    }

    // Tous vides : on reste où l'on est plutôt que de tourner dans le vide.
    return depuis;
  }

  /**
   * Lance le tour de rôle.
   *
   * ⚠️ **Navigateur uniquement.** Un minuteur posé au rendu serveur retiendrait
   * la réponse : le SSR attend que l'application se stabilise, et une horloge
   * qui bat toutes les sept secondes ne se stabilise jamais.
   */
  private demarrerLeTour(): void {
    if (!this.estNavigateur || this.minuteur) return;

    this.minuteur = setInterval(() => {
      // On ne tourne ni sous le curseur du visiteur (il est en train de lire la
      // carte qu'on ferait disparaître), ni dans un onglet en arrière-plan (ce
      // serait du réseau et du calcul pour personne).
      const pausePerdue =
        this.survolee && Date.now() - (this.pauseDepuis ?? 0) > PAUSE_MAX_MS;

      if ((this.survolee && !pausePerdue) || this.focusClavier || document.hidden) {
        return;
      }

      this.basculerVers(this.universSuivant(this.universCourant()));
    }, ROTATION_MS);

  }

  /**
   * ⚠️ Un minuteur survit au composant qui l'a posé : sans cet arrêt, quitter
   * l'accueil laisserait une horloge battre dans le vide — et rappeler l'API —
   * pour une page que plus personne ne regarde.
   */
  ngOnDestroy(): void {
    this.arreterLeTour();
  }

  private arreterLeTour(): void {
    if (this.minuteur) clearInterval(this.minuteur);
    this.minuteur = null;
  }

  /** Redonne un tour complet avant le prochain basculement automatique. */
  private relancerLeMinuteur(): void {
    this.arreterLeTour();
    this.demarrerLeTour();
  }

  /** Suspend le tour : la souris est entrée sur une pastille ou une carte. */
  protected suspendre(): void {
    this.survolee = true;
    this.pauseDepuis = Date.now();
  }

  /** Reprend le tour quand la souris quitte la pastille ou la carte. */
  protected reprendre(): void {
    this.survolee = false;
    this.pauseDepuis = null;
  }

  /**
   * Suspension au CLAVIER, et seulement au clavier.
   *
   * ⚠️ **Le piège qui a figé la vitrine** : un clic à la souris laisse le bouton
   * focalisé. En suspendant sur tout `focusin`, le tour s'arrêtait donc au
   * premier clic sur une pastille et ne repartait plus jamais — `focusout` ne
   * part qu'au moment où le focus s'en va, ce qui peut ne jamais arriver. Le
   * survol, lui, se terminait bien : d'où une vitrine figée alors que la souris
   * était partie depuis longtemps.
   *
   * `:focus-visible` distingue exactement les deux cas : il ne vaut que pour un
   * focus obtenu au clavier — le seul où quelqu'un a réellement besoin que la
   * vitrine l'attende.
   */
  protected focusEntrant(evenement: FocusEvent): void {
    const cible = evenement.target as HTMLElement | null;
    this.focusClavier = !!cible?.matches?.(':focus-visible');
  }

  /** Le focus quitte la vitrine : plus rien ne la retient. */
  protected focusSortant(): void {
    this.focusClavier = false;
  }

  /**
   * Choix manuel d'un univers (clic sur une pastille). Le minuteur est relancé :
   * sans cela, un clic serait balayé une seconde plus tard par le tour déjà en
   * cours, et le visiteur n'aurait pas le temps de regarder ce qu'il a demandé.
   */
  protected choisirUnivers(u: Universe): void {
    if (u === this.universCourant()) return;

    this.basculerVers(u);
    this.relancerLeMinuteur();
  }
}
