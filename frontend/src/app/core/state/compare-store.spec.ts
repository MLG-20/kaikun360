import { TestBed } from '@angular/core/testing';

import { COMPARE_MAX, CompareStore } from './compare-store';

/**
 * La sélection à comparer (F8.15.e) tient dans quatre règles, et chacune répare
 * un travers précis : elle **plafonne à quatre comme le serveur** et le dit au
 * lieu d'avaler le clic, elle **garde l'ordre de sélection** (le seul que
 * l'utilisateur ait en tête), elle **survit à un rechargement**, et elle se
 * relit **sans jamais faire confiance** à ce que contient le navigateur.
 */
describe('CompareStore', () => {
  let store: CompareStore;

  const nouveauStore = (): CompareStore => {
    TestBed.resetTestingModule();
    TestBed.configureTestingModule({});
    return TestBed.inject(CompareStore);
  };

  beforeEach(() => {
    localStorage.clear();
    store = nouveauStore();
  });

  afterEach(() => localStorage.clear());

  it('coche, décoche et compte les biens sélectionnés', () => {
    expect(store.count()).toBe(0);

    store.toggle(7);
    store.toggle(9);

    expect(store.count()).toBe(2);
    expect(store.has(7)).toBe(true);

    store.toggle(7);

    expect(store.has(7)).toBe(false);
    expect(store.ids()).toEqual([9]);
  });

  it('refuse le cinquième bien et le SIGNALE, au lieu d\'avaler le clic', () => {
    // Le serveur tronque à 4 (`->take(4)`) : sans ce refus explicite, le
    // cinquième se cocherait puis disparaîtrait de la comparaison sans un mot.
    for (let id = 1; id <= COMPARE_MAX; id++) {
      expect(store.toggle(id)).toBe(true);
    }

    expect(store.isFull()).toBe(true);
    expect(store.toggle(99)).toBe(false);
    expect(store.count()).toBe(COMPARE_MAX);
    expect(store.has(99)).toBe(false);

    // Une place libérée rouvre l'ajout.
    store.remove(1);
    expect(store.toggle(99)).toBe(true);
  });

  it('ne propose de comparer qu\'à partir de DEUX biens', () => {
    expect(store.canCompare()).toBe(false);

    store.toggle(3);
    expect(store.canCompare()).toBe(false);

    store.toggle(4);
    expect(store.canCompare()).toBe(true);
  });

  it('conserve l\'ORDRE de sélection, y compris après un rechargement', () => {
    store.toggle(12);
    store.toggle(3);
    store.toggle(8);

    // Un nouveau store = un rechargement de page : la sélection se relit.
    const rechargé = nouveauStore();

    expect(rechargé.ids()).toEqual([12, 3, 8]);
  });

  it('vide le stockage quand la sélection retombe à zéro', () => {
    store.toggle(5);
    store.clear();

    expect(store.count()).toBe(0);
    expect(localStorage.getItem('kaikun.compare.properties')).toBeNull();
    expect(nouveauStore().count()).toBe(0);
  });

  it('ignore un contenu de stockage corrompu ou hostile sans casser la page', () => {
    // Ce contenu vient du navigateur de l'utilisateur : il a pu être édité à la
    // main, ou écrit par une version antérieure du format.
    for (const brut of ['pas du json', '{"ids":[1]}', '["a",-3,0,null,2.5]']) {
      localStorage.setItem('kaikun.compare.properties', brut);
      expect(() => nouveauStore()).not.toThrow();
      expect(nouveauStore().ids()).toEqual([]);
    }

    // Une liste trop longue est ramenée au plafond du serveur.
    localStorage.setItem('kaikun.compare.properties', JSON.stringify([1, 2, 3, 4, 5, 6]));
    expect(nouveauStore().ids()).toEqual([1, 2, 3, 4]);
  });
});
