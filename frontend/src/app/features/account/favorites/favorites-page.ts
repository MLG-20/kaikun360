import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { FavoriteService } from '../../../core/api/favorite.service';
import { PageMeta } from '../../../core/api/pagination.model';
import { FavoriteStore } from '../../../core/state/favorite-store';
import { FavoriteItem } from '../../../models/favorite.model';
import { ListingCardComponent } from '../../../shared/components/listing-card/listing-card';
import {
  CatalogCard,
  UNIVERSE_FOR_FAVORITABLE,
  UNIVERSES,
} from '../../../shared/components/catalog/catalog.config';

/** Un favori prêt à afficher : sa clé, son type et son modèle de carte. */
interface FavoriteCard {
  /** Identifiant du favori (clé de liste + confirmation de retrait). */
  key: number;
  /** Type favorisable (pour le retrait). */
  type: FavoriteItem['type'];
  /** Modèle de vue de carte (même mappage que le catalogue de l'univers). */
  view: CatalogCard;
}

@Component({
  selector: 'app-favorites-page',
  imports: [RouterLink, ListingCardComponent],
  templateUrl: './favorites-page.html',
  styleUrl: './favorites-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
/**
 * Écran « Mes favoris » de l'espace client, monté sous `/mon-espace/favoris`.
 *
 * Liste paginée des favoris du client — désormais **tous univers confondus**
 * (biens, nuitées, véhicules, expériences, mobilité). Chaque favori est présenté
 * avec la MÊME carte que le catalogue de son univers (`app-listing-card` via
 * `UNIVERSES[...].toCard`), pour une continuité visuelle. Le client peut
 * **retirer** un favori (`DELETE /favorites/{type}/{id}`) via une confirmation
 * inline ; la carte quitte alors la liste et l'état partagé (cœurs du catalogue)
 * est resynchronisé.
 */
export class FavoritesPageComponent {
  private readonly favorites = inject(FavoriteService);
  private readonly store = inject(FavoriteStore);

  // — État de l'écran —
  protected readonly loading = signal(true);
  protected readonly loadError = signal(false);
  protected readonly items = signal<FavoriteItem[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);

  // — Retrait d'un favori (clé = id du favori) —
  protected readonly confirmKey = signal<number | null>(null);
  protected readonly busyKey = signal<number | null>(null);
  protected readonly removeError = signal<string | null>(null);

  /**
   * Favoris prêts à afficher : chaque favori mappé vers la carte de son univers
   * (source unique `UNIVERSES[...].toCard`, le même mappage que le catalogue). On
   * ignore un favori dont l'élément aurait disparu (favoritable absent).
   */
  protected readonly cards = computed<FavoriteCard[]>(() =>
    this.items()
      .filter((item) => item.favoritable != null)
      .map((item) => ({
        key: item.id,
        type: item.type,
        view: UNIVERSES[UNIVERSE_FOR_FAVORITABLE[item.type]].toCard(item.favoritable),
      })),
  );

  protected readonly hasPrev = computed(() => (this.meta()?.current_page ?? 1) > 1);
  protected readonly hasNext = computed(() => {
    const m = this.meta();
    return !!m && m.current_page < m.last_page;
  });

  constructor() {
    this.load(1);
  }

  /** Charge une page de favoris (remplace la liste affichée). */
  protected load(page: number): void {
    this.loading.set(true);
    this.loadError.set(false);
    this.confirmKey.set(null);
    this.removeError.set(null);
    this.favorites.myFavorites(page).subscribe({
      next: (res) => {
        this.items.set(res.data);
        this.meta.set(res.meta);
        this.loading.set(false);
        if (typeof window !== 'undefined') {
          window.scrollTo({ top: 0, behavior: 'smooth' });
        }
      },
      error: () => {
        this.loading.set(false);
        this.loadError.set(true);
      },
    });
  }

  protected prev(): void {
    if (this.hasPrev()) {
      this.load((this.meta()?.current_page ?? 2) - 1);
    }
  }

  protected next(): void {
    if (this.hasNext()) {
      this.load((this.meta()?.current_page ?? 0) + 1);
    }
  }

  // — Retrait d'un favori —

  /** Ouvre la demande de confirmation pour un favori donné. */
  protected askRemove(card: FavoriteCard): void {
    this.removeError.set(null);
    this.confirmKey.set(card.key);
  }

  /** Referme la demande de confirmation sans retirer. */
  protected dismissRemove(): void {
    this.confirmKey.set(null);
  }

  /** Confirme et exécute le retrait ; la carte quitte alors la liste. */
  protected confirmRemove(card: FavoriteCard): void {
    this.busyKey.set(card.key);
    this.removeError.set(null);
    this.favorites.remove(card.type, card.view.id).subscribe({
      next: () => {
        this.items.update((list) => list.filter((item) => item.id !== card.key));
        this.meta.update((m) => (m ? { ...m, total: Math.max(0, m.total - 1) } : m));
        this.busyKey.set(null);
        this.confirmKey.set(null);
        // Tient les cœurs du catalogue/accueil à jour (état partagé).
        this.store.refresh();
      },
      error: () => {
        this.busyKey.set(null);
        this.removeError.set("Le retrait n'a pas pu aboutir. Merci de réessayer plus tard.");
      },
    });
  }
}
