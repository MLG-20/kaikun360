import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { Observable, Subject, of } from 'rxjs';

import { CatalogService } from '../../core/api/catalog.service';
import { FavoriteStore } from '../../core/state/favorite-store';
import { HomePageComponent } from './home-page';

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
  function monter(options: { immobilierVide?: boolean; immobilierEnAttente?: Subject<unknown> } = {}) {
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
