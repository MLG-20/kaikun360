import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute } from '@angular/router';

import { CatalogComponent } from '../../shared/components/catalog/catalog';
import { SearchEngineComponent } from '../../shared/components/search-engine/search-engine';
import { UNIVERSES, Universe } from '../../shared/components/catalog/catalog.config';

/**
 * Page de résultats de recherche (F2.1) — route `/recherche`.
 *
 * Hôte générique du catalogue : l'univers vient du query param `univers`
 * (posé par le moteur de recherche), le reste des filtres est géré par le
 * composant `app-catalog` lui-même. En F2.3, chaque page d'univers réutilisera
 * directement `<app-catalog [universe]="...">` avec un univers figé.
 */
@Component({
  selector: 'app-catalog-page',
  imports: [SearchEngineComponent, CatalogComponent],
  templateUrl: './catalog-page.html',
  styleUrl: './catalog-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly params = toSignal(this.route.queryParamMap);

  /** Univers demandé, validé contre le registre (repli sur « immobilier »). */
  readonly universe = computed<Universe>(() => {
    const raw = this.params()?.get('univers');
    return raw && raw in UNIVERSES ? (raw as Universe) : 'immobilier';
  });

  /** Libellé lisible de l'univers courant, pour le titre. */
  readonly universeLabel = computed(() => UNIVERSES[this.universe()].label);
}
