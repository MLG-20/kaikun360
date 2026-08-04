import { TestBed } from '@angular/core/testing';

import { BookingIntentStore } from './booking-intent-store';

/**
 * Le panier de réservation (F8.13) tient dans quatre règles, et chacune répare
 * un travers précis : il rend la saisie **à la bonne fiche**, il ne la rend
 * **qu'une fois**, il **périme** au bout d'une heure, et il ne casse rien quand
 * le stockage du navigateur est refusé. Ce sont ces quatre-là qu'on verrouille.
 */
describe('BookingIntentStore', () => {
  let store: BookingIntentStore;

  beforeEach(() => {
    sessionStorage.clear();
    TestBed.configureTestingModule({});
    store = TestBed.inject(BookingIntentStore);
  });

  afterEach(() => {
    sessionStorage.clear();
    vi.useRealTimers();
  });

  it('rend au retour la saisie mise de côté avant la connexion', () => {
    store.remember('stay', '12', { arrival: '2026-09-01', departure: '2026-09-04', guests: 2 });

    expect(store.take('stay', '12')).toEqual({
      arrival: '2026-09-01',
      departure: '2026-09-04',
      guests: 2,
    });
  });

  it('ne rend rien à une AUTRE fiche, ni à un autre univers', () => {
    store.remember('stay', '12', { arrival: '2026-09-01' });

    // Même univers, autre logement : ces dates ne le concernent pas.
    expect(store.take('stay', '13')).toBeNull();
    // Même identifiant, autre univers : le véhicule 12 n'est pas la nuitée 12.
    expect(store.take('vehicle', '12')).toBeNull();
    // …et la fiche d'origine la retrouve, aucune de ces tentatives ne l'a mangée.
    expect(store.take('stay', '12')).not.toBeNull();
  });

  it('se consomme : la même saisie n’est rendue qu’une seule fois', () => {
    store.remember('mobility', '5', { guests: 3 });

    expect(store.take('mobility', '5')).toEqual({ guests: 3 });
    // Revenir sur la fiche plus tard ne doit pas ressusciter une saisie oubliée.
    expect(store.take('mobility', '5')).toBeNull();
  });

  it('périme au bout d’une heure (des dates retrouvées le lendemain n’ont plus de sens)', () => {
    vi.useFakeTimers();
    store.remember('experience', '7', { start_date: '2026-09-10', seats: 2 });

    vi.advanceTimersByTime(3_600_001);

    expect(store.take('experience', '7')).toBeNull();
  });

  it('reste silencieux quand le navigateur refuse le stockage', () => {
    const setItem = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('quota');
    });

    // Aucune exception ne doit remonter : le panier est un confort, son échec ne
    // doit pas empêcher le visiteur d'aller se connecter.
    expect(() => store.remember('stay', '1', { arrival: '2026-09-01' })).not.toThrow();
    setItem.mockRestore();

    expect(store.take('stay', '1')).toBeNull();
  });
});
