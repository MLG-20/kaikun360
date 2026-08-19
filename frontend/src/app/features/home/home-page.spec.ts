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
        { provide: NewsService, useValue: { list: () => of(options.actualites ?? []) } },
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
  const article: NewsArticle = {
    id: 1,
    title: 'Un article',
    excerpt: 'Résumé',
    body: null,
    image: 'https://cdn.test/news.jpg',
    videoFile: null,
    videoUrl: null,
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
        { provide: NewsService, useValue: { list: () => of(options.actualites ?? []) } },
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

  it('affiche la grille des univers quand aucune actualité n’est publiée', () => {
    const fixture = monter({ actualites: [] });
    const hote = fixture.nativeElement as HTMLElement;

    expect(hote.querySelector('.univers-grid')).toBeTruthy();
    expect(hote.querySelector('.news-grid')).toBeFalsy();

    fixture.destroy();
  });

  it('remplace la grille des univers par les actualités dès qu’il y en a une', () => {
    const fixture = monter({ actualites: [article] });
    const hote = fixture.nativeElement as HTMLElement;

    expect(hote.querySelector('.news-grid')).toBeTruthy();
    expect(hote.querySelector('.univers-grid')).toBeFalsy();
    expect(hote.textContent).toContain('Un article');

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

  /**
   * Carrousel des actualités (F16.2) : demande explicite de l'utilisateur
   * après avoir vu la grille jugée trop encombrante à terme — une carte à la
   * fois, qui cède la place à la suivante et boucle. Même patron de minuteur
   * que la vitrine (garde-fou de pause), déjà couvert côté vitrine ; ici on
   * ne protège que ce qui est propre aux actualités : une seule carte à la
   * fois, et la boucle après le dernier article.
   */
  it('n’affiche qu’une seule carte à la fois même avec plusieurs actualités publiées', () => {
    const second: NewsArticle = { ...article, id: 2, title: 'Un second article' };
    const fixture = monter({ actualites: [article, second] });
    const hote = fixture.nativeElement as HTMLElement;

    expect(hote.querySelectorAll('.news-card').length).toBe(1);
    expect(hote.textContent).toContain('Un article');
    expect(hote.textContent).not.toContain('Un second article');

    fixture.destroy();
  });

  it('passe à l’actualité suivante puis boucle sur la première', () => {
    vi.useFakeTimers();

    try {
      const second: NewsArticle = { ...article, id: 2, title: 'Un second article' };
      const fixture = monter({ actualites: [article, second] });
      const hote = fixture.nativeElement as HTMLElement;

      expect(hote.textContent).toContain('Un article');

      // 90 s : cadence propre aux actualités (NEWS_ROTATION_MS), nettement
      // plus lente que le reste du site (7 s) pour laisser une vidéo
      // démarrer avant d'être coupée par l'aperçu automatique.
      vi.advanceTimersByTime(90000);
      fixture.detectChanges();
      expect(hote.textContent).toContain('Un second article');

      vi.advanceTimersByTime(90000);
      fixture.detectChanges();
      expect(hote.textContent).toContain('Un article');
      expect(hote.textContent).not.toContain('Un second article');

      fixture.destroy();
    } finally {
      vi.useRealTimers();
    }
  });

  it('bascule automatiquement même sur une actualité vidéo tant que rien n’a été choisi à la main', () => {
    vi.useFakeTimers();

    try {
      const video: NewsArticle = { ...article, videoUrl: 'https://www.youtube.com/embed/abc' };
      const second: NewsArticle = { ...article, id: 2, title: 'Un second article' };
      const fixture = monter({ actualites: [video, second] });
      const hote = fixture.nativeElement as HTMLElement;

      // L'aperçu automatique bascule quoi qu'il arrive, vidéo comprise —
      // un clic sur une pastille (voir le test suivant) ne le fige plus.
      vi.advanceTimersByTime(90000);
      fixture.detectChanges();
      expect(hote.textContent).toContain('Un second article');

      fixture.destroy();
    } finally {
      vi.useRealTimers();
    }
  });

  it('un clic sur une pastille bascule immédiatement, sans figer le défilement automatique', () => {
    vi.useFakeTimers();

    try {
      const second: NewsArticle = { ...article, id: 2, title: 'Un second article' };
      const fixture = monter({ actualites: [article, second] });
      const hote = fixture.nativeElement as HTMLElement;

      // `.featured-tab` est aussi le nom des pastilles d'univers de la vitrine
      // (réutilisées telles quelles ici, sans CSS neuf) : on cible celles des
      // actualités par leur groupe ARIA dédié.
      const pastilles = Array.from(
        hote.querySelectorAll<HTMLButtonElement>('[aria-label="Actualités"] .featured-tab'),
      );
      expect(pastilles.length).toBe(2);

      pastilles[1].click();
      fixture.detectChanges();

      // Le clic change l'actualité affichée, ce qui recrée le bloc DOM
      // (`@for` suivi par l'id de l'article courant) : on ré-interroge les
      // pastilles au lieu de réutiliser la référence capturée avant le clic.
      const pastillesApres = Array.from(
        hote.querySelectorAll<HTMLButtonElement>('[aria-label="Actualités"] .featured-tab'),
      );
      expect(hote.textContent).toContain('Un second article');
      expect(pastillesApres[1].classList.contains('featured-tab--active')).toBe(true);

      // Le choix ne fige rien : le minuteur reprend après NEWS_ROTATION_MS
      // comme d'habitude, et boucle sur la première actualité.
      vi.advanceTimersByTime(90000);
      fixture.detectChanges();
      expect(hote.textContent).toContain('Un article');
      expect(hote.textContent).not.toContain('Un second article');

      fixture.destroy();
    } finally {
      vi.useRealTimers();
    }
  });
});
