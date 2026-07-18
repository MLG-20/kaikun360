import { Injectable, computed, effect, inject, signal } from '@angular/core';
import { Router } from '@angular/router';

import { FavoritableRef, FavoriteIds } from '../../models/favorite.model';
import { FavoriteService } from '../api/favorite.service';
import { AuthService } from '../auth/auth.service';

/** Ensemble d'ids favoris vide (contrat stable : un tableau par type). */
const EMPTY_FAVORITE_IDS: FavoriteIds = {
  property: [],
  stay: [],
  vehicle: [],
  experience: [],
  mobility: [],
};

/**
 * État PARTAGÉ des favoris de l'utilisateur (favoris polymorphes, tous univers).
 *
 * Source unique consommée par toutes les surfaces qui affichent un cœur
 * (catalogue, accueil, fiches…) : elle garde la liste des ids favoris et gère la
 * bascule, si bien qu'un ajout depuis le catalogue se reflète immédiatement
 * partout. La liste se (re)charge automatiquement selon l'état de connexion ;
 * un clic anonyme redirige vers la connexion (avec retour).
 */
@Injectable({ providedIn: 'root' })
export class FavoriteStore {
  private readonly favorites = inject(FavoriteService);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  /** Ids favoris par type. */
  private readonly ids = signal<FavoriteIds>(EMPTY_FAVORITE_IDS);
  /** Favoris en cours de bascule (clé « type:id »). */
  private readonly busy = signal<ReadonlySet<string>>(new Set());

  /** Exposé en lecture seule (utile pour d'éventuels compteurs). */
  readonly favoriteIds = computed(() => this.ids());

  constructor() {
    // (Re)charge les favoris quand l'état de connexion change ; les vide en
    // anonyme (aucun appel voué au 401, y compris côté serveur SSR).
    effect(() => {
      if (this.auth.isAuthenticated()) {
        this.favorites.ids().subscribe({
          next: (res) => this.ids.set(res.data),
          error: () => this.ids.set(EMPTY_FAVORITE_IDS),
        });
      } else {
        this.ids.set(EMPTY_FAVORITE_IDS);
      }
    });
  }

  /** L'élément est-il dans mes favoris ? */
  isFavorited(ref: FavoritableRef): boolean {
    return this.ids()[ref.type]?.includes(ref.id) ?? false;
  }

  /** Un appel favori est-il en cours pour cet élément ? */
  isBusy(ref: FavoritableRef): boolean {
    return this.busy().has(this.key(ref));
  }

  /**
   * Ajoute / retire l'élément des favoris (mise à jour optimiste). Anonyme →
   * redirection vers la connexion avec retour à la page courante.
   */
  toggle(ref: FavoritableRef | null): void {
    if (!ref) {
      return;
    }
    if (!this.auth.isAuthenticated()) {
      this.router.navigate(['/auth/connexion'], { queryParams: { redirect: this.router.url } });
      return;
    }

    const key = this.key(ref);
    if (this.busy().has(key)) {
      return;
    }
    this.busy.update((set) => new Set(set).add(key));

    const wasFavorited = this.isFavorited(ref);
    const request = wasFavorited
      ? this.favorites.remove(ref.type, ref.id)
      : this.favorites.add(ref.type, ref.id);

    request.subscribe({
      next: () => {
        this.setFavorited(ref, !wasFavorited);
        this.clearBusy(key);
      },
      error: () => this.clearBusy(key),
    });
  }

  /** Force le rechargement de la liste (ex. après un retrait depuis l'écran favoris). */
  refresh(): void {
    if (this.auth.isAuthenticated()) {
      this.favorites.ids().subscribe({ next: (res) => this.ids.set(res.data) });
    }
  }

  private key(ref: FavoritableRef): string {
    return `${ref.type}:${ref.id}`;
  }

  private setFavorited(ref: FavoritableRef, favorited: boolean): void {
    this.ids.update((ids) => {
      const current = ids[ref.type] ?? [];
      const next = favorited
        ? [...new Set([...current, ref.id])]
        : current.filter((id) => id !== ref.id);
      return { ...ids, [ref.type]: next };
    });
  }

  private clearBusy(key: string): void {
    this.busy.update((set) => {
      const next = new Set(set);
      next.delete(key);
      return next;
    });
  }
}
