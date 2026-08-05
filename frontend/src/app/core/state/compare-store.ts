import { isPlatformBrowser } from '@angular/common';
import { Injectable, PLATFORM_ID, computed, inject, signal } from '@angular/core';

/** Clé de persistance : une seule sélection de comparaison à la fois. */
const STORAGE_KEY = 'kaikun.compare.properties';

/**
 * Plafond de la comparaison. **Imposé par le serveur** : `GET /properties/compare`
 * tronque à 4 (`->take(4)`). Le reproduire ici n'est pas une duplication de règle
 * métier mais la condition pour que l'écran puisse le DIRE — sans ce plafond
 * côté client, un cinquième bien se cocherait puis disparaîtrait silencieusement
 * de la comparaison.
 */
export const COMPARE_MAX = 4;

/**
 * La **sélection de biens à comparer** (F8.15.e).
 *
 * `GET /properties/compare` existe depuis **B2.5** et n'avait aucun appelant :
 * le CDC §2.1 range pourtant la « comparaison » parmi les fonctions de Kaikun
 * Immo. Il manquait uniquement de quoi CHOISIR les biens — d'où ce store, seule
 * source de la sélection pour toutes les surfaces qui l'affichent (catalogue,
 * page de comparaison), sur le modèle de `FavoriteStore`.
 *
 * **Pourquoi ce n'est pas un favori.** Un favori est un choix durable, rattaché
 * au compte et stocké en base ; comparer est un geste de session, ouvert aux
 * **visiteurs anonymes** — c'est précisément au moment où l'on hésite entre deux
 * biens qu'on n'a pas encore de compte. Exiger une connexion ici mettrait le mur
 * avant l'envie, comme le formulaire de réservation avant F8.13.
 *
 * **Pourquoi `localStorage` et non `sessionStorage`** — l'inverse du choix fait
 * pour le panier de réservation (`BookingIntentStore`) : on compare des biens
 * sur plusieurs jours, en rouvrant le site, et une sélection de 4 identifiants
 * publics n'a rien de sensible. Aucune péremption pour la même raison ; un bien
 * dépublié entre-temps est simplement absent de la réponse du serveur, ce que la
 * page de comparaison signale.
 *
 * ⚠️ **Uniquement l'immobilier.** Le serveur n'expose de comparaison que pour
 * les biens ; ni les nuitées, ni les véhicules, ni les circuits n'ont d'endpoint
 * équivalent. D'où un store typé « biens » plutôt qu'un store polymorphe qui
 * promettrait quatre univers dont trois répondraient 404.
 */
@Injectable({ providedIn: 'root' })
export class CompareStore {
  private readonly isBrowser = isPlatformBrowser(inject(PLATFORM_ID));

  /** Ids sélectionnés, dans l'ordre où ils ont été cochés. */
  private readonly selected = signal<readonly number[]>(this.read());

  /** La sélection courante, en lecture seule. */
  readonly ids = computed(() => this.selected());

  /** Nombre de biens sélectionnés (compteur de la barre d'action). */
  readonly count = computed(() => this.selected().length);

  /** Y a-t-il de quoi comparer ? Un bien seul ne se compare à rien. */
  readonly canCompare = computed(() => this.selected().length >= 2);

  /** La sélection est-elle pleine ? (le serveur n'en traitera pas plus). */
  readonly isFull = computed(() => this.selected().length >= COMPARE_MAX);

  /** Ce bien est-il dans la sélection ? */
  has(id: number): boolean {
    return this.selected().includes(id);
  }

  /**
   * Coche / décoche un bien. Renvoie `false` si l'ajout a été **refusé** parce
   * que la sélection est pleine — la surface appelante peut alors le dire, au
   * lieu de laisser un clic sans effet visible.
   */
  toggle(id: number): boolean {
    const current = this.selected();

    if (current.includes(id)) {
      this.write(current.filter((existing) => existing !== id));
      return true;
    }

    if (current.length >= COMPARE_MAX) {
      return false;
    }

    this.write([...current, id]);
    return true;
  }

  /** Retire un bien (geste de la page de comparaison). */
  remove(id: number): void {
    this.write(this.selected().filter((existing) => existing !== id));
  }

  /** Vide la sélection. */
  clear(): void {
    this.write([]);
  }

  /** Écrit la sélection en mémoire ET sur le disque du navigateur. */
  private write(ids: readonly number[]): void {
    this.selected.set(ids);

    if (!this.isBrowser) {
      return;
    }
    try {
      if (ids.length) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
      } else {
        localStorage.removeItem(STORAGE_KEY);
      }
    } catch {
      // Navigation privée, quota, stockage refusé : la comparaison est un
      // confort, elle ne doit jamais casser la navigation. La sélection reste
      // valable en mémoire pour la durée de la page.
    }
  }

  /**
   * Relit la sélection au démarrage. Défensif de bout en bout : ce contenu vient
   * du navigateur de l'utilisateur, il a pu être édité à la main ou écrit par
   * une version antérieure du format.
   *
   * ⚠️ Rien au rendu SERVEUR : `localStorage` n'y existe pas, et une sélection
   * rendue côté serveur différerait de celle du client — l'hydratation
   * (`provideClientHydration`) échouerait sur cette divergence, exactement le
   * piège rencontré sur le bouton Google en F8.15.
   */
  private read(): readonly number[] {
    if (!this.isBrowser) {
      return [];
    }
    try {
      const brut = localStorage.getItem(STORAGE_KEY);
      const parsed: unknown = brut ? JSON.parse(brut) : null;
      if (!Array.isArray(parsed)) {
        return [];
      }
      return parsed
        .filter((value): value is number => Number.isInteger(value) && (value as number) > 0)
        .slice(0, COMPARE_MAX);
    } catch {
      return [];
    }
  }
}
