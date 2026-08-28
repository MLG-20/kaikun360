import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { Observable, Subject, of } from 'rxjs';

import { CatalogService } from '../../core/api/catalog.service';
import { HeroBanner, HeroService } from '../../core/api/hero.service';
import { HomeHero, HomeHeroService } from '../../core/api/home-hero.service';
import { NewsArticle, NewsService } from '../../core/api/news.service';
import { FavoriteStore } from '../../core/state/favorite-store';
import { HomePageComponent } from './home-page';

/**
 * Faux service de bandeaux (F12/F16.1) : on décide clé par clé ce que le
 * back-office a saisi, sans passer par une requête HTTP réelle.
 */
function fakeHeroService(banners: Record<string, HeroBanner> = {}) {
  return { banner: (key: string): HeroBanner | null => banners[key] ?? null };
}

/**
 * Tests de la vitrine tournante de l'accueil (F13.5).
 *
 * Ce qu'ils protègent n'est PAS « la rotation marche » — cela se voit à l'œil en
 * dix secondes. Ils gardent les trois comportements qui n'arrivent que dans des
 * situations qu'on ne reproduit pas à la main :
 *
 *   1. un univers **sans annonce** doit être sauté — c'est le cas normal d'une
 *      plateforme qui démarre, et la vitrine s'arrêterait sinon sur « Le
 *      catalogue s'enrichit » devant un client ;
 *   2. une réponse **arrivée en retard** ne doit pas écraser la grille affichée
 *      — invisible sur un réseau local, pénible sur une connexion lente ;
 *   3. le tour doit **repartir après une pause** — le défaut trouvé à l'usage le
 *      jour de la livraison, deux fois de suite.
 *
 * Comme le reste du projet, on interroge le **DOM rendu** plutôt que les
 * membres du composant : c'est ce que voit le visiteur qui fait foi.
 */
describe('HomePageComponent — vitrine tournante', () => {
  /** Une page d'API, réduite à ce que lit le registre des univers. */
  function page(data: unknown[]) {
    return of({ data } as never);
  }

  const bien = { id: 1, title: 'Villa R+1', location: { commune: 'Ngor', region: 'Dakar' }, price_xof: 800_000, verification_level: 'unverified', photo_url: null };
  const nuitee = { id: 2, property: { title: 'Studio meublé', location: { commune: 'Saly', region: 'Thiès' }, verification_level: 'unverified', photo_url: null }, price_per_night_xof: 35_000 };
  const vehicule = { id: 3, brand: 'Toyota', model: 'Hiace', type_label: 'Minibus', capacity: 14, price_per_day_xof: 60_000, has_driver: true, photo_url: null };
  const circuit = { id: 4, title: 'Île de Gorée', destination: 'Gorée', duration_days: 2, price_xof: 29_489, photo_url: null };
  const depart = { id: 5, type_label: 'Navette', departure: 'Thiès', destination: 'Dakar', price_xof: 55_133, photo_url: null };

  /** Nombre d'appels par univers : sert à prouver que le cache tient. */
  let appels: Record<string, number>;

  /**
   * Monte la page avec un catalogue de test. `immobilierEnAttente` permet de
   * retenir la réponse du premier univers pour rejouer le cas de la réponse
   * tardive.
   */
  function monter(options: {
    immobilierVide?: boolean;
    immobilierEnAttente?: Subject<unknown>;
    actualites?: NewsArticle[];
    heroMedia?: HomeHero;
    banners?: Record<string, HeroBanner>;
  } = {}) {
    appels = { properties: 0, stays: 0, vehicles: 0, experiences: 0, mobility: 0 };

    const catalogue = {
      properties: () => {
        appels['properties']++;
        if (options.immobilierEnAttente) {
          return options.immobilierEnAttente as unknown as Observable<never>;
        }

        return page(options.immobilierVide ? [] : [bien]);
      },
      stays: () => (appels['stays']++, page([nuitee])),
      vehicles: () => (appels['vehicles']++, page([vehicule])),
      experiences: () => (appels['experiences']++, page([circuit])),
      mobilityServices: () => (appels['mobility']++, page([depart])),
    };

    TestBed.configureTestingModule({
      imports: [HomePageComponent],
      providers: [
        provideRouter([]),
        { provide: CatalogService, useValue: catalogue },
        {
          provide: FavoriteStore,
          useValue: { isFavorited: () => false, isBusy: () => false, toggle: () => undefined },
        },
        {
          provide: HomeHeroService,
          useValue: { get: () => of(options.heroMedia ?? { images: [], video: null }) },
        },
        {
          provide: NewsService,
          useValue: { list: () => of({ articles: options.actualites ?? [], discoverCardsCount: 4 }) },
        },
        { provide: HeroService, useValue: fakeHeroService(options.banners) },
      ],
    });

    const fixture = TestBed.createComponent(HomePageComponent);
    fixture.detectChanges();

    return fixture;
  }

  /** Titre courant de la vitrine (il suit l'univers affiché). */
  function titre(fixture: { nativeElement: unknown }): string {
    const hote = fixture.nativeElement as HTMLElement;

    return hote.querySelector('#featured-title')?.textContent?.trim() ?? '';
  }

  /** Libellé de la pastille active. */
  function pastilleActive(fixture: { nativeElement: unknown }): string {
    const hote = fixture.nativeElement as HTMLElement;

    return hote.querySelector('.featured-tab--active')?.textContent?.trim() ?? '';
  }

  afterEach(() => {
    TestBed.resetTestingModule();
  });

  it('saute un univers sans annonce au lieu de montrer une grille vide', () => {
    const fixture = monter({ immobilierVide: true });

    // L'immobilier n'a rien : la vitrine est passée d'elle-même aux nuitées.
    expect(pastilleActive(fixture)).toBe('Nuitées');
    expect(titre(fixture)).toContain('séjours');

    const hote = fixture.nativeElement as HTMLElement;
    expect(hote.querySelectorAll('app-listing-card').length).toBe(1);

    fixture.destroy();
  });

  it('ignore une réponse arrivée après un changement d’univers', () => {
    const enRetard = new Subject<unknown>();
    const fixture = monter({ immobilierEnAttente: enRetard });
    const hote = fixture.nativeElement as HTMLElement;

    // Le visiteur n'attend pas : il demande le transport pendant le chargement.
    const pastilles = Array.from(hote.querySelectorAll<HTMLButtonElement>('.featured-tab'));
    pastilles.find((b) => b.textContent?.includes('Transport'))?.click();
    fixture.detectChanges();

    expect(pastilleActive(fixture)).toBe('Transport');

    // L'immobilier répond enfin. Il ne doit PAS reprendre la place.
    enRetard.next({ data: [bien] });
    enRetard.complete();
    fixture.detectChanges();

    expect(pastilleActive(fixture)).toBe('Transport');
    expect(titre(fixture)).toContain('véhicules');
    expect(hote.textContent).toContain('Toyota Hiace');

    fixture.destroy();
  });

  it('ne recharge pas un univers déjà vu (cache)', () => {
    const fixture = monter();
    const hote = fixture.nativeElement as HTMLElement;
    const pastilles = Array.from(hote.querySelectorAll<HTMLButtonElement>('.featured-tab'));
    const clic = (libelle: string) => {
      pastilles.find((b) => b.textContent?.trim() === libelle)?.click();
      fixture.detectChanges();
    };

    clic('Transport');
    const apresPremierPassage = appels['vehicles'];

    clic('Tourisme');
    clic('Transport');

    // Retour sur un univers déjà chargé : aucun appel supplémentaire.
    expect(appels['vehicles']).toBe(apresPremierPassage);

    fixture.destroy();
  });

  it('la bande de la vitrine reprend le bandeau F12 de l’univers affiché (F16.1)', () => {
    const fixture = monter({
      banners: { immobilier: { image: 'https://cdn.test/immo.jpg', eyebrow: null, title: null, lead: null } },
    });
    const hote = fixture.nativeElement as HTMLElement;
    const bande = hote.querySelector('.featured-band') as HTMLElement;

    // L'accueil démarre sur l'univers Immobilier, qui a une annonce et un bandeau.
    expect(bande.classList.contains('featured-band--photo')).toBe(true);
    expect(bande.querySelector('.k-photo-layer')?.getAttribute('style')).toContain('immo.jpg');

    fixture.destroy();
  });

  it('reprend le tour quand la souris quitte la vitrine', () => {
    vi.useFakeTimers();

    try {
      const fixture = monter();
      const hote = fixture.nativeElement as HTMLElement;
      const pastilles = hote.querySelector('.featured-tabs') as HTMLElement;

      expect(pastilleActive(fixture)).toBe('Immobilier');

      // Sous le curseur, le tour attend : on ne fait pas disparaître ce qu'on lit.
      pastilles.dispatchEvent(new MouseEvent('mouseenter'));
      vi.advanceTimersByTime(8000);
      fixture.detectChanges();
      expect(pastilleActive(fixture)).toBe('Immobilier');

      // ⚠️ Le cœur du test : la souris partie, le tour DOIT repartir. Il est
      // resté figé deux fois — pause posée sur toute la section, puis focus
      // laissé par un clic — et rien à l'écran ne l'expliquait.
      pastilles.dispatchEvent(new MouseEvent('mouseleave'));
      vi.advanceTimersByTime(8000);
      fixture.detectChanges();
      expect(pastilleActive(fixture)).toBe('Nuitées');

      fixture.destroy();
    } finally {
      vi.useRealTimers();
    }
  });
});

/**
 * Tests F15 : bascule entre la section « Actualités Kaikun » et la grille des
 * univers, et photo de fond du héros.
 *
 * Le comportement protégé n'est pas l'affichage lui-même (visible à l'œil)
 * mais la RÈGLE de bascule : elle doit suivre le CONTENU (au moins un article
 * publié), pas un réglage qu'on aurait pu oublier de synchroniser.
 */
describe('HomePageComponent — actualités & héros (F15)', () => {
  const carte: NewsArticle = {
    id: 1,
    title: 'Une carte',
    excerpt: 'Résumé',
    body: null,
    image: 'https://cdn.test/news.jpg',
    videoFile: null,
    videoUrl: null,
    linkUrl: 'https://kaikun360.com/immobilier',
    linkLabel: 'Voir les biens',
  };

  function page(data: unknown[]) {
    return of({ data } as never);
  }

  function monter(
    options: { actualites?: NewsArticle[]; heroMedia?: HomeHero; banners?: Record<string, HeroBanner> } = {},
  ) {
    const catalogue = {
      properties: () => page([]),
      stays: () => page([]),
      vehicles: () => page([]),
      experiences: () => page([]),
      mobilityServices: () => page([]),
    };

    TestBed.configureTestingModule({
      imports: [HomePageComponent],
      providers: [
        provideRouter([]),
        { provide: CatalogService, useValue: catalogue },
        {
          provide: FavoriteStore,
          useValue: { isFavorited: () => false, isBusy: () => false, toggle: () => undefined },
        },
        {
          provide: HomeHeroService,
          useValue: { get: () => of(options.heroMedia ?? { images: [], video: null }) },
        },
        {
          provide: NewsService,
          useValue: { list: () => of({ articles: options.actualites ?? [], discoverCardsCount: 4 }) },
        },
        { provide: HeroService, useValue: fakeHeroService(options.banners) },
      ],
    });

    const fixture = TestBed.createComponent(HomePageComponent);
    fixture.detectChanges();

    return fixture;
  }

  afterEach(() => {
    TestBed.resetTestingModule();
  });

  it('affiche la grille des univers quand aucune carte n’est publiée', () => {
    const fixture = monter({ actualites: [] });
    const hote = fixture.nativeElement as HTMLElement;

    expect(hote.querySelector('.univers-grid')).toBeTruthy();
    expect(hote.querySelector('app-news-card-mini-list')).toBeFalsy();

    fixture.destroy();
  });

  it('remplace la grille des univers par les cartes dès qu’il y en a une', () => {
    const fixture = monter({ actualites: [carte] });
    const hote = fixture.nativeElement as HTMLElement;

    expect(hote.querySelector('app-news-card-mini-list')).toBeTruthy();
    expect(hote.querySelector('.univers-grid')).toBeFalsy();
    expect(hote.textContent).toContain('Une carte');

    fixture.destroy();
  });

  it('n’ajoute aucun média de fond au héros sans photo ni vidéo au back-office', () => {
    const fixture = monter({ heroMedia: { images: [], video: null } });
    const hote = fixture.nativeElement as HTMLElement;
    const hero = hote.querySelector('.hero') as HTMLElement;

    expect(hero.classList.contains('hero--image')).toBe(false);
    expect(hero.style.backgroundImage).toBe('');
    expect(hote.querySelector('.hero-media')).toBeFalsy();

    fixture.destroy();
  });

  it('affiche la première photo du diaporama en fond du héros', () => {
    const fixture = monter({
      heroMedia: { images: ['https://cdn.test/hero1.jpg', 'https://cdn.test/hero2.jpg'], video: null },
    });
    const hote = fixture.nativeElement as HTMLElement;
    const hero = hote.querySelector('.hero') as HTMLElement;

    expect(hero.classList.contains('hero--image')).toBe(true);
    expect(hero.style.backgroundImage).toContain('hero1.jpg');

    fixture.destroy();
  });

  it('une vidéo remplace entièrement le diaporama', () => {
    const fixture = monter({
      heroMedia: {
        images: ['https://cdn.test/hero1.jpg'],
        video: { file: 'https://cdn.test/clip.mp4', url: null },
      },
    });
    const hote = fixture.nativeElement as HTMLElement;
    const hero = hote.querySelector('.hero') as HTMLElement;
    const video = hote.querySelector('.hero-media video') as HTMLVideoElement | null;

    // Aucun `background-image` : la couche vidéo occupe seule le fond.
    expect(hero.style.backgroundImage).toBe('');
    expect(video).toBeTruthy();
    expect(video?.getAttribute('src')).toBe('https://cdn.test/clip.mp4');

    fixture.destroy();
  });

  /**
   * Fonds photo de sections (F16.1). Ce qui compte n'est pas « l'image
   * s'affiche » (visible à l'œil) mais que l'ABSENCE de bandeau saisi laisse
   * chaque section strictement identique à avant cette tranche — c'est la
   * garantie, héritée de `HeroCatalog`, qu'une plateforme où personne n'a
   * rien chargé au back-office ne change pas d'apparence.
   */
  it('ne pose aucun fond photo tant que rien n’a été saisi au back-office', () => {
    const fixture = monter({});
    const hote = fixture.nativeElement as HTMLElement;

    expect(hote.querySelector('.diaspora')?.classList.contains('diaspora--photo')).toBe(false);
    expect(hote.querySelector('.cta-final-inner')?.classList.contains('k-photo-section')).toBe(false);
    expect(hote.querySelector('.featured-band')?.classList.contains('featured-band--photo')).toBe(false);
    expect(hote.querySelectorAll('.k-photo-layer').length).toBe(0);

    fixture.destroy();
  });

  it('habille la section Diaspora avec le bandeau `home-diaspora`', () => {
    const fixture = monter({
      banners: { 'home-diaspora': { image: 'https://cdn.test/diaspora.jpg', eyebrow: null, title: null, lead: null } },
    });
    const hote = fixture.nativeElement as HTMLElement;
    const section = hote.querySelector('.diaspora') as HTMLElement;

    expect(section.classList.contains('diaspora--photo')).toBe(true);
    expect(section.querySelector('.k-photo-layer')?.getAttribute('style')).toContain('diaspora.jpg');

    fixture.destroy();
  });

  it('habille l’appel final avec le bandeau `home-cta`', () => {
    const fixture = monter({
      banners: { 'home-cta': { image: 'https://cdn.test/cta.jpg', eyebrow: null, title: null, lead: null } },
    });
    const hote = fixture.nativeElement as HTMLElement;
    const carte = hote.querySelector('.cta-final-inner') as HTMLElement;

    expect(carte.classList.contains('k-photo-section')).toBe(true);
    expect(carte.querySelector('.k-photo-layer')?.getAttribute('style')).toContain('cta.jpg');

    fixture.destroy();
  });

  // ── F17 : cartes libres (image/vidéo + lien, sans texte) ──

  it('une ligne avec un corps de texte rédigé n’est plus affichée sur l’accueil (retrait du carrousel, 2026-08-21)', () => {
    // Demande explicite du client : « enlevons Actualité Kaikun, laissons les
    // petites cartes seulement ». Une ligne avec `body` n'a plus de carrousel
    // pour l'accueillir sur cette page, et n'est pas non plus une carte
    // (le critère carte exige l'ABSENCE de `body`) — elle disparaît donc de
    // l'accueil, la grille des univers reprend sa place.
    const redige: NewsArticle = { ...carte, id: 2, body: '<p>Texte complet</p>', linkUrl: null };
    const fixture = monter({ actualites: [redige] });
    const hote = fixture.nativeElement as HTMLElement;

    expect(hote.querySelector('.univers-grid')).toBeTruthy();
    expect(hote.querySelector('app-news-card-mini-list')).toBeFalsy();

    fixture.destroy();
  });

  it('une ligne sans texte ni lien ne devient pas une carte (régression du filtre)', () => {
    const sansLien: NewsArticle = { ...carte, linkUrl: null };
    const fixture = monter({ actualites: [sansLien] });
    const hote = fixture.nativeElement as HTMLElement;

    expect(hote.querySelector('.univers-grid')).toBeTruthy();
    expect(hote.querySelector('app-news-card-mini-list')).toBeFalsy();

    fixture.destroy();
  });

  it('une ligne sans texte MAIS avec un lien devient une carte', () => {
    const fixture = monter({ actualites: [carte] });
    const hote = fixture.nativeElement as HTMLElement;

    expect(hote.querySelector('.univers-grid')).toBeFalsy();
    expect(hote.querySelector('app-news-card-mini-list')).toBeTruthy();
    expect(hote.textContent).toContain('Une carte');

    fixture.destroy();
  });

  it('plafonne les cartes libres à 4', () => {
    const cartes: NewsArticle[] = Array.from({ length: 6 }, (_, i) => ({
      ...carte,
      id: 10 + i,
      title: `Carte ${i + 1}`,
      linkUrl: `https://kaikun360.com/carte-${i}`,
    }));
    const fixture = monter({ actualites: cartes });
    const hote = fixture.nativeElement as HTMLElement;

    const rendues = hote.querySelectorAll('app-news-card-mini-list a');
    expect(rendues.length).toBe(4);

    fixture.destroy();
  });

  // ── F17.1 : colonne vidéo, séparée des cartes image (2026-08-21) ──

  it('une ligne avec vidéo mais sans lien affiche la vidéo seule, sans grille des univers', () => {
    const video: NewsArticle = { ...carte, id: 6, linkUrl: null, videoUrl: 'https://www.youtube.com/embed/abc' };
    const fixture = monter({ actualites: [video] });
    const hote = fixture.nativeElement as HTMLElement;

    expect(hote.querySelector('.univers-grid')).toBeFalsy();
    expect(hote.querySelector('.news-videos')).toBeTruthy();
    expect(hote.querySelector('app-news-card-mini-list')).toBeFalsy();

    fixture.destroy();
  });

  it('une carte reste une image même quand elle porte aussi une vidéo', () => {
    // La vidéo d'une ligne alimente la colonne de gauche EN PLUS de la carte
    // à droite si la ligne a aussi un lien — mais la carte, elle, n'affiche
    // jamais la vidéo à la place de son image (décision du client, F17.1).
    const carteAvecVideo: NewsArticle = { ...carte, videoUrl: 'https://www.youtube.com/embed/abc' };
    const fixture = monter({ actualites: [carteAvecVideo] });
    const hote = fixture.nativeElement as HTMLElement;

    expect(hote.querySelector('.news-videos')).toBeTruthy();
    expect(hote.querySelector('app-news-card-mini-list img')).toBeTruthy();
    expect(hote.querySelector('app-news-card-mini-list iframe')).toBeFalsy();
    expect(hote.querySelector('app-news-card-mini-list video')).toBeFalsy();

    fixture.destroy();
  });

  it('vidéo + carte s’affichent côte à côte dans le même cadre', () => {
    const video: NewsArticle = { ...carte, id: 7, linkUrl: null, videoUrl: 'https://www.youtube.com/embed/abc' };
    const fixture = monter({ actualites: [video, carte] });
    const hote = fixture.nativeElement as HTMLElement;

    expect(hote.querySelector('.news-card--split')).toBeTruthy();
    expect(hote.querySelector('.news-card--split .news-videos')).toBeTruthy();
    expect(hote.querySelector('.news-card--split app-news-card-mini-list')).toBeTruthy();

    fixture.destroy();
  });

  // ── F17.2 : carrousel vidéo (les vidéos tournent, pas les cartes) ──

  it('n’affiche qu’une seule vidéo à la fois même avec plusieurs vidéos publiées', () => {
    const video1: NewsArticle = { ...carte, id: 8, linkUrl: null, title: 'Vidéo un', videoUrl: 'https://www.youtube.com/embed/un' };
    const video2: NewsArticle = { ...carte, id: 9, linkUrl: null, title: 'Vidéo deux', videoUrl: 'https://www.youtube.com/embed/deux' };
    const fixture = monter({ actualites: [video1, video2] });
    const hote = fixture.nativeElement as HTMLElement;

    expect(hote.querySelectorAll('.news-video-wrap').length).toBe(1);

    fixture.destroy();
  });

  it('passe à la vidéo suivante puis boucle sur la première', () => {
    vi.useFakeTimers();

    try {
      const video1: NewsArticle = { ...carte, id: 8, linkUrl: null, videoUrl: 'https://www.youtube.com/embed/un' };
      const video2: NewsArticle = { ...carte, id: 9, linkUrl: null, videoUrl: 'https://www.youtube.com/embed/deux' };
      const fixture = monter({ actualites: [video1, video2] });
      const hote = fixture.nativeElement as HTMLElement;

      const iframeSrc = () => (hote.querySelector('.news-video') as HTMLIFrameElement)?.getAttribute('src');
      expect(iframeSrc()).toContain('/embed/un');

      // 90 s : cadence propre aux vidéos (VIDEO_ROTATION_MS).
      vi.advanceTimersByTime(90000);
      fixture.detectChanges();
      expect(iframeSrc()).toContain('/embed/deux');

      vi.advanceTimersByTime(90000);
      fixture.detectChanges();
      expect(iframeSrc()).toContain('/embed/un');

      fixture.destroy();
    } finally {
      vi.useRealTimers();
    }
  });

  it('aucune pastille : la vidéo se choisit en survolant sa carte', () => {
    const video: NewsArticle = { ...carte, id: 8, linkUrl: null, videoUrl: 'https://www.youtube.com/embed/un' };
    // Même id que `carte` (1) : cette ligne est à la fois une vidéo ET une
    // carte, c'est ce id partagé qui fait le lien entre les deux colonnes.
    const carteAvecVideo: NewsArticle = { ...carte, videoUrl: 'https://www.youtube.com/embed/deux' };
    const fixture = monter({ actualites: [video, carteAvecVideo] });
    const hote = fixture.nativeElement as HTMLElement;

    expect(hote.querySelector('[aria-label="Vidéos"]')).toBeFalsy();

    const iframe = () => hote.querySelector('.news-video') as HTMLIFrameElement;
    expect(iframe().getAttribute('src')).toContain('/embed/un');

    const lienCarte = hote.querySelector('app-news-card-mini-list a') as HTMLAnchorElement;
    lienCarte.dispatchEvent(new MouseEvent('mouseenter'));
    fixture.detectChanges();
    expect(iframe().getAttribute('src')).toContain('/embed/deux');

    lienCarte.dispatchEvent(new MouseEvent('mouseleave'));
    fixture.detectChanges();
    expect(iframe().getAttribute('src')).toContain('/embed/un');

    fixture.destroy();
  });
});
