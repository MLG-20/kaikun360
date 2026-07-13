import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { Router } from '@angular/router';

import { UNIVERSES, Universe } from '../catalog/catalog.config';

/**
 * Moteur de recherche global (F2.1).
 *
 * Point d'entrée unique du catalogue : l'utilisateur choisit un univers, saisit
 * une ville/des mots-clés et un budget, puis lance la recherche. Le composant
 * navigue vers la page de résultats `/recherche` en passant des paramètres qui
 * correspondent EXACTEMENT aux filtres du backend (`q`, `price_max`) ; la page
 * de résultats et son catalogue prennent le relais pour affiner.
 *
 * Note : la « ville » est pour l'instant mappée sur la recherche plein-texte
 * (`q`). Le filtrage géographique par identifiant de commune/région arrivera en
 * F2.3 avec le sélecteur de localité dédié.
 */
@Component({
  selector: 'app-search-engine',
  imports: [],
  templateUrl: './search-engine.html',
  styleUrl: './search-engine.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class SearchEngineComponent {
  private readonly router = inject(Router);

  /** Onglets d'univers, dérivés du registre du catalogue. */
  readonly universes = Object.values(UNIVERSES).map((u) => ({ key: u.key, label: u.label }));

  /** Univers sélectionné (immobilier par défaut). */
  readonly selected = signal<Universe>('immobilier');
  /** Ville ou mots-clés (→ filtre `q`). */
  readonly query = signal('');
  /** Budget maximum (→ filtre `price_max`). */
  readonly budget = signal('');

  select(universe: Universe): void {
    this.selected.set(universe);
  }

  /** Lance la recherche : navigue vers `/recherche` avec les filtres non vides. */
  submit(): void {
    const queryParams: Record<string, string> = { univers: this.selected() };
    const q = this.query().trim();
    const budget = this.budget().trim();
    if (q) {
      queryParams['q'] = q;
    }
    if (budget) {
      queryParams['price_max'] = budget;
    }
    this.router.navigate(['/recherche'], { queryParams });
  }
}
