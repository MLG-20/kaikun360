import { isPlatformBrowser } from '@angular/common';
import {
  ChangeDetectionStrategy,
  Component,
  OnDestroy,
  OnInit,
  PLATFORM_ID,
  computed,
  inject,
  signal,
} from '@angular/core';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { RouterLink } from '@angular/router';
import { Observable, catchError, map, of } from 'rxjs';

import { CatalogService } from '../../core/api/catalog.service';
import { HeroService } from '../../core/api/hero.service';
import { HomeHero, HomeHeroService } from '../../core/api/home-hero.service';
import { NewsArticle, NewsService } from '../../core/api/news.service';
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
import { CountUpDirective } from '../../shared/directives/count-up.directive';
import { UniverseStripComponent } from '../../shared/components/universe-strip/universe-strip';
import { ParallaxDirective } from '../../shared/directives/parallax.directive';
import { RevealDirective } from '../../shared/directives/reveal.directive';
import { NewsCardMiniListComponent } from './news-card-mini-list/news-card-mini-list';

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

/**
 * Article d'actualité, avec son URL d'embed vidéo déjà sécurisée.
 *
 * ⚠️ **Précalculée une seule fois, ici, jamais dans le gabarit.**
 * `DomSanitizer.bypassSecurityTrustResourceUrl` crée un nouvel objet à chaque
 * appel ; appelée depuis `[src]="videoEmbed(a.videoUrl)"` dans le template,
 * elle en fabrique un NEUF à chaque cycle de détection — et Angular compare
 * les `SafeResourceUrl` par référence. Les minuteurs du héros et de la
 * vitrine (7 s chacun) redéclenchent la détection, l'iframe recevait donc une
 * « nouvelle » source toutes les quelques secondes : le navigateur la
 * rechargeait, et la vidéo YouTube repartait de zéro sans jamais aller à son
 * terme. Une valeur stable, posée une fois à la réception des articles, fait
 * disparaître le problème.
 */
interface NewsArticleView extends NewsArticle {
  videoEmbedUrl: SafeResourceUrl | null;
}

/**
 * Ajoute lecture automatique, boucle et son coupé à une URL d'embed déjà
 * assainie (YouTube ou Vimeo).
 *
 * Sans ça, avec plusieurs actualités vidéo dans le flux, chacune resterait
 * figée sur son image de prévisualisation tant que le visiteur ne clique pas
 * dessus — ce qui n'attire jamais l'œil. Le son reste coupé par défaut : les
 * politiques des navigateurs bloquent de toute façon toute lecture
 * automatique non coupée.
 */
function withAutoplayLoop(embedUrl: string): string {
  const [base, query] = embedUrl.split('?');
  const params = new URLSearchParams(query ?? '');

  const youtube = base.match(/^https:\/\/www\.youtube\.com\/embed\/([\w-]+)/);
  if (youtube) {
    params.set('autoplay', '1');
    params.set('mute', '1');
    params.set('loop', '1');
    params.set('playlist', youtube[1]);
    return `${base}?${params.toString()}`;
  }

  if (base.startsWith('https://player.vimeo.com/video/')) {
    params.set('autoplay', '1');
    params.set('muted', '1');
    params.set('loop', '1');
    return `${base}?${params.toString()}`;
  }

  return embedUrl;
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
 * Cadence du carrousel des vidéos (F17.2) : nettement plus lente que le reste
 * du site (7 s) pour laisser à une vidéo le temps de démarrer avant d'être
 * coupée. ⚠️ Reste un simple minuteur, sans exception pour une vidéo choisie
 * via une pastille : au bout de ce délai, ça bascule même si la vidéo en
 * cours est plus longue — même choix que l'ancien carrousel d'articles.
 */
const VIDEO_ROTATION_MS = 90000;

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
  imports: [
    ListingCardComponent,
    RouterLink,
    RevealDirective,
    CountUpDirective,
    ParallaxDirective,
    UniverseStripComponent,
    NewsCardMiniListComponent,
  ],
  templateUrl: './home-page.html',
  styleUrl: './home-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class HomePageComponent implements OnInit, OnDestroy {
  private readonly catalog = inject(CatalogService);
  private readonly seo = inject(SeoService);
  private readonly homeHero = inject(HomeHeroService);
  private readonly news = inject(NewsService);
  private readonly heroBanners = inject(HeroService);
  private readonly sanitizer = inject(DomSanitizer);
  /** État partagé des favoris (cœurs sur les biens en vedette). */
  protected readonly favorites = inject(FavoriteStore);

  /**
   * Héros de l'accueil (F15.1) : un diaporama de photos, une courte vidéo, ou
   * rien (fond de marque d'origine). Distinct des bandeaux F12 (une image par
   * page) — c'est le seul endroit du site à porter plusieurs médias.
   */
  protected readonly heroMedia = signal<HomeHero>({ images: [], video: null });

  /** Une vidéo, quand elle existe, REMPLACE entièrement le diaporama. */
  protected readonly heroHasVideo = computed(() => !!this.heroMedia().video);

  /**
   * URL d'embed assainie de la vidéo du héros, si elle est fournie par un
   * lien (pas un fichier déposé) — un `computed()`, donc mémoïsée : elle ne
   * se recalcule QUE si `heroMedia` change réellement, jamais à chaque cycle
   * de détection. Même piège corrigé que `NewsArticleView` : appeler
   * `bypassSecurityTrustResourceUrl` depuis le gabarit fabrique un nouvel
   * objet à chaque rendu, ce que les minuteurs du héros et de la vitrine
   * (7 s chacun) redéclenchaient sans cesse — l'iframe se rechargeait et la
   * vidéo ne finissait jamais.
   */
  protected readonly heroVideoEmbedUrl = computed(() => {
    const url = this.heroMedia().video?.url;

    return url ? this.sanitizer.bypassSecurityTrustResourceUrl(withAutoplayLoop(url)) : null;
  });

  /** Index de la photo actuellement affichée dans le diaporama. */
  protected readonly heroSlideIndex = signal(0);

  /** Valeur CSS prête à l'emploi pour la photo courante du diaporama. */
  protected readonly heroSlideBackground = computed(() => {
    const images = this.heroMedia().images;
    const url = images[this.heroSlideIndex() % Math.max(images.length, 1)];

    return url ? `url("${encodeURI(url)}")` : null;
  });

  /** Minuteur du diaporama du héros (navigateur uniquement). */
  private heroMinuteur: ReturnType<typeof setInterval> | null = null;

  /**
   * Fonds photo de sections de l'accueil (F16.1) : réutilise le mécanisme des
   * bandeaux F12 (`HeroCatalog`) plutôt qu'un système dédié — deux clés de
   * plus (`home-diaspora`, `home-cta`), aucune nouvelle route, aucun nouvel
   * écran back-office (l'onglet Bandeaux existant les liste déjà).
   */
  protected readonly diasporaBackground = computed(() => this.photoUrl('home-diaspora'));
  protected readonly ctaBackground = computed(() => this.photoUrl('home-cta'));

  private photoUrl(key: string): string | null {
    const url = this.heroBanners.banner(key)?.image ?? null;

    return url ? `url("${encodeURI(url)}")` : null;
  }

  /**
   * Lignes « Actualités » publiées, telles que reçues de l'API.
   *
   * ⚠️ **C'est `cartesLibres`, pas ce signal, qui décide de la Section 2 de
   * l'accueil** (voir `aDesActualites` ci-dessous).
   */
  protected readonly actualites = signal<NewsArticleView[]>([]);

  /**
   * Petites cartes IMAGE (titre + lien) de la section « Actualités ».
   *
   * ⚠️ **Seule une ligne SANS texte rédigé mais AVEC un lien devient une
   * carte** (F17, 2026-08-21) — une ligne avec un corps de texte (vrai
   * article) n'est plus affichée sur l'accueil du tout depuis la demande du
   * client de 2026-08-21 : « enlevons Actualité Kaikun, laissons les petites
   * cartes seulement ». L'ancien carrousel d'articles (rotation, pastilles,
   * page `/actualites/:id`) a été retiré de cette page pour cette raison.
   *
   * ⚠️ **Toujours une image, jamais de vidéo** (F17.1, 2026-08-21) : la vidéo
   * d'une ligne, si elle en a une, n'est plus montrée ICI mais dans
   * `videosActualites` — une carte ne PERD pas son statut de carte pour
   * autant si elle porte aussi une vidéo, les deux listes sont indépendantes.
   *
   * ⚠️ **Plafonnée à 4** — ce sont des repères fixes que le client choisit de
   * montrer « en ce moment », pas un contenu qui défile.
   */
  protected readonly cartesLibres = computed(() =>
    this.actualites()
      .filter((a) => !a.body && !!a.linkUrl)
      .slice(0, 4),
  );

  /**
   * Vidéos de la section « Actualités » (F17.1, 2026-08-21) : la colonne de
   * GAUCHE du grand bloc, les cartes image (`cartesLibres`) occupant la
   * colonne de DROITE — demande explicite du client, après avoir vu la
   * vidéo écraser une carte trop petite pour l'accueillir.
   *
   * ⚠️ Toute ligne portant un fichier vidéo déposé OU un lien d'embed
   * alimente cette colonne, QU'ELLE SOIT AUSSI une carte (avec un lien) ou
   * non — une même ligne peut apparaître ici ET à droite.
   */
  protected readonly videosActualites = computed(() =>
    this.actualites().filter((a) => !!a.videoFile || !!a.videoUrl),
  );

  /**
   * Carrousel des vidéos (F17.2, 2026-08-21) : une vidéo à la fois, qui cède
   * la place à la suivante et boucle — demande explicite du client, pour que
   * les vidéos ne s'empilent pas les unes sous les autres comme les cartes
   * image le font. Même mécanique de tour de rôle que la vitrine du
   * catalogue (`demarrerLeTour`) : minuteur avec garde-fou de pause, pas un
   * simple `clearInterval` au survol (voir `suspendre`/`reprendre` plus bas).
   *
   * ⚠️ **Sans pastille** (F17.3, 2026-08-21) : le choix manuel ne se fait
   * plus en cliquant un numéro, mais en survolant la CARTE que la vidéo
   * représente — voir `carteSurvoleeId` et `onSurvolCarte` plus bas. Une
   * ligne peut être à la fois une vidéo (id) et une carte (même id) ; c'est
   * ce id partagé qui fait le lien entre les deux colonnes.
   */
  protected readonly videoIndex = signal(0);
  protected readonly carteSurvoleeId = signal<number | null>(null);
  protected readonly videoCourante = computed(() => {
    const liste = this.videosActualites();
    const survolee = this.carteSurvoleeId();
    const correspondante = survolee !== null ? liste.find((v) => v.id === survolee) : undefined;

    if (correspondante) return correspondante;

    return liste.length ? liste[this.videoIndex() % liste.length] : null;
  });

  private videoMinuteur: ReturnType<typeof setInterval> | null = null;
  private videoSurvolee = false;
  private videoPauseDepuis: number | null = null;

  /**
   * ⚠️ **C'est ce signal qui décide de la Section 2 de l'accueil** : au moins
   * une carte OU une vidéo → la section Actualités s'affiche à la place de la
   * grille des univers ; aucune des deux → la grille reprend sa place.
   * Bascule automatique, sans geste de l'équipe — demande explicite de
   * l'utilisateur (2026-08-16). La navigation vers les univers reste de
   * toute façon accessible par le méga-menu de l'en-tête.
   */
  protected readonly aDesActualites = computed(
    () => this.cartesLibres().length > 0 || this.videosActualites().length > 0,
  );

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
   * Fond de la bande d'en-tête de la vitrine (F16.1) : reprend le bandeau F12
   * déjà chargé pour la page de l'univers affiché (`immobilier`, `nuitees`…) —
   * aucune photo dédiée à saisir en plus, l'équipe l'a déjà déposée pour la
   * page /immobilier, /nuitees, etc.
   */
  protected readonly universBackground = computed(() => this.photoUrl(this.universCourant()));

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
    this.chargerActualites();
    this.chargerHeroMedia();
    this.referencer();
  }

  /**
   * Charge les actualités publiées. Une panne réseau n'affiche pas d'erreur —
   * comme les bandeaux (F12), c'est de la décoration : la page retombe alors
   * sur la grille des univers, son état sans surcharge.
   *
   * L'URL d'embed vidéo est assainie ICI (voir `NewsArticleView`), pas dans
   * le gabarit. Même modèle de confiance que la carte de la page Contact
   * (`bypassSecurityTrustResourceUrl`) : la valeur vient d'un agent titulaire
   * de `gerer:parametres`, pas d'un visiteur.
   */
  private chargerActualites(): void {
    this.news
      .list()
      .pipe(
        map((articles) =>
          articles.map((a) => ({
            ...a,
            videoEmbedUrl: a.videoUrl
              ? this.sanitizer.bypassSecurityTrustResourceUrl(withAutoplayLoop(a.videoUrl))
              : null,
          })),
        ),
        catchError(() => of([] as NewsArticleView[])),
      )
      .subscribe((articles) => {
        this.actualites.set(articles);
        this.demarrerLeCarrouselVideos();
      });
  }

  /**
   * Lance l'aperçu automatique des vidéos (F17.2). Voir `demarrerLeTour`
   * (vitrine) pour le détail du garde-fou de pause — même patron, appliqué
   * ici à un minuteur distinct pour ne pas coupler les deux carrousels.
   */
  private demarrerLeCarrouselVideos(): void {
    if (!this.estNavigateur || this.videoMinuteur) return;
    if (this.videosActualites().length < 2) return;

    this.videoMinuteur = setInterval(() => {
      const pausePerdue =
        this.videoSurvolee && Date.now() - (this.videoPauseDepuis ?? 0) > PAUSE_MAX_MS;

      if ((this.videoSurvolee && !pausePerdue) || document.hidden) return;

      this.videoIndex.update((i) => i + 1);
    }, VIDEO_ROTATION_MS);
  }

  /** Suspend le carrousel des vidéos : la souris est dessus. */
  protected suspendreVideo(): void {
    this.videoSurvolee = true;
    this.videoPauseDepuis = Date.now();
  }

  /** Reprend le carrousel des vidéos quand la souris s'en va. */
  protected reprendreVideo(): void {
    this.videoSurvolee = false;
    this.videoPauseDepuis = null;
  }

  /**
   * Survol d'une carte (F17.3, 2026-08-21) : remplace le clic sur une
   * pastille — survoler une carte affiche SA vidéo si elle en a une (même
   * id), et suspend le défilement automatique le temps du survol, comme s'il
   * s'agissait d'un survol direct de la vidéo. `id` vaut `null` en sortie de
   * survol (`mouseleave`) : le défilement automatique reprend alors sa
   * course normale, `videoCourante` retombant sur `videoIndex`.
   */
  protected onSurvolCarte(id: number | null): void {
    this.carteSurvoleeId.set(id);

    if (id !== null) {
      this.suspendreVideo();
    } else {
      this.reprendreVideo();
    }
  }

  /**
   * Charge le héros de l'accueil (diaporama ou vidéo). Même tolérance de
   * panne que les actualités : c'est de la décoration, pas un contenu dont
   * l'absence doit se voir comme une erreur.
   */
  private chargerHeroMedia(): void {
    this.homeHero
      .get()
      .pipe(catchError(() => of({ images: [], video: null } as HomeHero)))
      .subscribe((media) => {
        this.heroMedia.set(media);
        this.demarrerLeDiaporamaDuHeros();
      });
  }

  /**
   * Fait défiler le diaporama de photos du héros, une image toutes les 7 s —
   * même cadence que la vitrine du catalogue. Rien à faire si une vidéo est
   * présente (elle remplace le diaporama) ou s'il y a moins de deux photos.
   */
  private demarrerLeDiaporamaDuHeros(): void {
    if (!this.estNavigateur || this.heroMinuteur) return;
    if (this.heroHasVideo() || this.heroMedia().images.length < 2) return;

    this.heroMinuteur = setInterval(() => {
      this.heroSlideIndex.update((i) => i + 1);
    }, ROTATION_MS);
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
    if (this.heroMinuteur) clearInterval(this.heroMinuteur);
    if (this.videoMinuteur) clearInterval(this.videoMinuteur);
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
