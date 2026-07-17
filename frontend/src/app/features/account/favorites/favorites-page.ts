import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { FavoriteService } from '../../../core/api/favorite.service';
import { PageMeta } from '../../../core/api/pagination.model';
import { Property } from '../../../models/property.model';
import { ListingCardComponent } from '../../../shared/components/listing-card/listing-card';
import { CatalogCard, UNIVERSES } from '../../../shared/components/catalog/catalog.config';

@Component({
  selector: 'app-favorites-page',
  imports: [RouterLink, ListingCardComponent],
  templateUrl: './favorites-page.html',
  styleUrl: './favorites-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
/**
 * Écran « Mes favoris » de l'espace client (F3.5), monté sous
 * `/mon-espace/favoris`. Liste paginée des biens immobiliers que le client a
 * sauvegardés (`GET /favorites`, plus récents d'abord).
 *
 * Chaque favori est présenté avec la MÊME carte que le catalogue public
 * (`app-listing-card`) pour une continuité visuelle : cliquer mène à la fiche du
 * bien. Le client peut **retirer** un bien de ses favoris (`DELETE
 * /properties/{id}/favorite`) via une confirmation inline ; la carte disparaît
 * alors de la liste.
 */
export class FavoritesPageComponent {
  private readonly favorites = inject(FavoriteService);

  // — État de l'écran —
  protected readonly loading = signal(true);
  protected readonly loadError = signal(false);
  protected readonly items = signal<Property[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);

  // — Retrait d'un favori —
  /** Bien dont la confirmation de retrait est affichée. */
  protected readonly confirmId = signal<number | null>(null);
  /** Bien dont le retrait est en cours (requête en vol). */
  protected readonly busyId = signal<number | null>(null);
  protected readonly removeError = signal<string | null>(null);

  /**
   * Favoris prêts à afficher : chaque bien accompagné de son modèle de vue de
   * carte, calculé UNE fois (source unique `UNIVERSES.immobilier.toCard`, le
   * même mappage que le catalogue immobilier public).
   */
  protected readonly cards = computed(() =>
    this.items().map((property) => ({
      property,
      view: UNIVERSES['immobilier'].toCard(property) as CatalogCard,
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
    this.confirmId.set(null);
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

  /** Ouvre la demande de confirmation pour un bien donné. */
  protected askRemove(property: Property): void {
    this.removeError.set(null);
    this.confirmId.set(property.id);
  }

  /** Referme la demande de confirmation sans retirer. */
  protected dismissRemove(): void {
    this.confirmId.set(null);
  }

  /** Confirme et exécute le retrait ; la carte quitte alors la liste. */
  protected confirmRemove(property: Property): void {
    this.busyId.set(property.id);
    this.removeError.set(null);
    this.favorites.remove(property.id).subscribe({
      next: () => {
        this.items.update((list) => list.filter((p) => p.id !== property.id));
        this.meta.update((m) => (m ? { ...m, total: Math.max(0, m.total - 1) } : m));
        this.busyId.set(null);
        this.confirmId.set(null);
      },
      error: () => {
        this.busyId.set(null);
        this.removeError.set(
          "Le retrait n'a pas pu aboutir. Merci de réessayer plus tard.",
        );
      },
    });
  }
}
