import { ChangeDetectionStrategy, Component, computed, inject, input, signal } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute, Router } from '@angular/router';
import { of } from 'rxjs';
import { catchError, switchMap, tap } from 'rxjs/operators';

import { Paginated } from '../../../core/api/pagination.model';
import { CatalogService } from '../../../core/api/catalog.service';
import { ListingCardComponent } from '../listing-card/listing-card';
import {
  CatalogCard,
  FilterValues,
  SORT_OPTIONS,
  UNIVERSES,
  Universe,
} from './catalog.config';

/**
 * Catalogue filtrable, triable et paginé (F2.1) — brique réutilisée sur toutes
 * les pages d'univers (F2.3+).
 *
 * Générique : l'univers à afficher est passé via `input()`. Tout l'état (filtres,
 * tri, page) vit dans l'URL (query params) → les recherches sont partageables,
 * mémorisables et compatibles avec le bouton « précédent » du navigateur. Le
 * composant se contente de lire ces paramètres, d'appeler le `CatalogService`
 * correspondant (via le registre `UNIVERSES`) et de rendre une grille de
 * `app-listing-card`.
 */
@Component({
  selector: 'app-catalog',
  imports: [ListingCardComponent],
  templateUrl: './catalog.html',
  styleUrl: './catalog.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogComponent {
  private readonly catalog = inject(CatalogService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  /** Univers à afficher (fourni par la page hôte). */
  readonly universe = input.required<Universe>();

  readonly sortOptions = SORT_OPTIONS;

  /** Configuration de l'univers courant (filtres, fetch, mapper). */
  readonly config = computed(() => UNIVERSES[this.universe()]);

  /** Query params courants, sous forme de signal réactif. */
  private readonly queryParams = toSignal(this.route.queryParamMap);

  /** Vrai pendant le chargement d'une page de résultats. */
  readonly loading = signal(false);
  /** Vrai si la dernière requête a échoué (réseau / serveur). */
  readonly failed = signal(false);

  /**
   * Filtres actifs déduits de l'URL : uniquement les clés reconnues par l'univers
   * courant (+ tri + page), pour ne jamais transmettre de paramètre parasite.
   */
  private readonly activeFilters = computed<FilterValues>(() => {
    const params = this.queryParams();
    const config = this.config();
    const values: FilterValues = {};
    if (!params) {
      return values;
    }
    for (const field of config.filters) {
      const raw = params.get(field.key);
      if (raw) {
        values[field.key] = raw;
      }
    }
    const sort = params.get('sort');
    if (sort && config.hasSort) {
      values['sort'] = sort;
    }
    const page = params.get('page');
    if (page) {
      values['page'] = Number(page);
    }
    return values;
  });

  /**
   * Résultat paginé courant. Recalculé automatiquement à chaque changement
   * d'univers ou de filtres. `switchMap` annule la requête précédente encore en
   * vol lorsqu'un nouveau jeu de filtres arrive.
   */
  private readonly result = toSignal(
    toObservable(
      computed(() => ({ config: this.config(), filters: this.activeFilters() })),
    ).pipe(
      switchMap(({ config, filters }) => {
        this.loading.set(true);
        this.failed.set(false);
        return config.fetch(this.catalog, filters).pipe(
          catchError(() => {
            this.failed.set(true);
            return of<Paginated<unknown> | null>(null);
          }),
          tap(() => this.loading.set(false)),
        );
      }),
    ),
    { initialValue: null as Paginated<unknown> | null },
  );

  /** Cartes prêtes à afficher (résultat mappé par la config de l'univers). */
  readonly cards = computed<CatalogCard[]>(() => {
    const res = this.result();
    if (!res) {
      return [];
    }
    const toCard = this.config().toCard;
    return res.data.map((item) => toCard(item));
  });

  /** Métadonnées de pagination (ou null tant qu'aucun résultat). */
  readonly meta = computed(() => this.result()?.meta ?? null);

  /** Vrai quand la recherche a abouti mais ne renvoie aucun résultat. */
  readonly empty = computed(() => !this.loading() && !this.failed() && this.cards().length === 0);

  /** Valeur courante d'un champ de filtre (pour l'affichage des contrôles). */
  currentValue(key: string): string {
    return this.queryParams()?.get(key) ?? '';
  }

  /** Valeur de tri courante (défaut « recent »). */
  currentSort(): string {
    return this.queryParams()?.get('sort') ?? 'recent';
  }

  /** Applique un filtre et revient à la page 1. Valeur vide = filtre retiré. */
  onFilterChange(key: string, value: string): void {
    this.patchParams({ [key]: value || null, page: null });
  }

  /** Applique/retire un filtre booléen (case à cocher). */
  onBooleanChange(key: string, checked: boolean): void {
    this.patchParams({ [key]: checked ? 'true' : null, page: null });
  }

  /** Change le tri et revient à la page 1. */
  onSortChange(value: string): void {
    this.patchParams({ sort: value === 'recent' ? null : value, page: null });
  }

  /** Réinitialise tous les filtres et le tri de l'univers courant. */
  reset(): void {
    const cleared: Record<string, null> = { sort: null, page: null };
    for (const field of this.config().filters) {
      cleared[field.key] = null;
    }
    this.patchParams(cleared);
  }

  /** Navigue vers une page de résultats. */
  goToPage(page: number): void {
    this.patchParams({ page });
  }

  /** Met à jour les query params en fusionnant avec ceux déjà présents. */
  private patchParams(patch: Record<string, string | number | null>): void {
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: patch,
      queryParamsHandling: 'merge',
    });
  }
}
