import { ChangeDetectionStrategy, Component, inject, input, linkedSignal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute, Router } from '@angular/router';

import { UNIVERSES, Universe } from '../catalog/catalog.config';

/**
 * Moteur de recherche global (F2.1).
 *
 * Point d'entrée du catalogue : l'utilisateur choisit un univers, saisit une
 * ville/des mots-clés et un budget, puis lance la recherche. Le composant navigue
 * vers `/recherche` avec des paramètres qui correspondent EXACTEMENT aux filtres
 * du backend (`q`, `price_max`) ; le catalogue prend ensuite le relais pour
 * affiner.
 *
 * ⚠️ **Deux défauts corrigés en F8.11**, tous deux visibles sur la page de
 * résultats :
 *
 *  1. **Les onglets d'univers ne reflétaient pas l'URL.** `selected` partait
 *     toujours d'« immobilier », si bien qu'en arrivant sur
 *     `/recherche?univers=nuitees` l'onglet actif affichait *Immobilier* pendant
 *     que le catalogue, juste dessous, titrait *Nuitées*. Deux contrôles de la
 *     même page se contredisaient. Les trois champs sont désormais des
 *     `linkedSignal` **branchés sur l'URL** : ils repartent de l'adresse à chaque
 *     changement, tout en restant librement modifiables entre-temps.
 *
 *  2. **Cliquer un onglet ne faisait rien** tant qu'on n'appuyait pas sur
 *     « Rechercher ». Or ces onglets portent `role="tab"` : on attend d'eux
 *     qu'ils changent le contenu, pas qu'ils arment un formulaire. Sur la page
 *     de résultats (`live`), la sélection **navigue immédiatement** en gardant
 *     les critères déjà saisis. Sur la page d'accueil, où il n'y a aucun
 *     résultat à changer, l'onglet se contente de préparer la recherche.
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
  private readonly route = inject(ActivatedRoute);

  /**
   * Le moteur surplombe-t-il des résultats ? Vrai sur `/recherche`, faux sur la
   * page d'accueil. Détermine si un onglet navigue tout de suite.
   */
  readonly live = input(false);

  private readonly params = toSignal(this.route.queryParamMap);

  /** Onglets d'univers, dérivés du registre du catalogue. */
  readonly universes = Object.values(UNIVERSES).map((u) => ({ key: u.key, label: u.label }));

  /**
   * Univers sélectionné. Suit l'URL (repli « immobilier » si le paramètre est
   * absent ou inconnu — un univers inventé à la main ne doit pas casser la page).
   */
  readonly selected = linkedSignal<Universe>(() => {
    const raw = this.params()?.get('univers');
    return raw && raw in UNIVERSES ? (raw as Universe) : 'immobilier';
  });

  /** Ville ou mots-clés (→ filtre `q`). */
  readonly query = linkedSignal(() => this.params()?.get('q') ?? '');

  /** Budget maximum (→ filtre `price_max`). */
  readonly budget = linkedSignal(() => this.params()?.get('price_max') ?? '');

  /**
   * Choisit un univers. Sur la page de résultats, applique le changement
   * aussitôt : un onglet qui s'allume sans rien changer se lit comme un bug.
   */
  select(universe: Universe): void {
    this.selected.set(universe);
    if (this.live()) {
      this.submit();
    }
  }

  /** Lance la recherche : navigue vers `/recherche` avec les filtres non vides. */
  submit(): void {
    const queryParams: Record<string, string> = { univers: this.selected() };
    const q = this.query().trim();
    const budget = this.budget().trim();

    // ⚠️ Chaque univers n'accepte pas les mêmes filtres. La « ville » est
    // envoyée en recherche plein-texte (`q`) partout où elle existe, sauf en
    // MOBILITÉ : un départ programmé n'a pas de champ libre côté serveur, mais
    // il a une ville de départ — c'est là que le terme a un sens.
    if (q) {
      queryParams[this.selected() === 'mobilite' ? 'departure' : 'q'] = q;
    }
    // ⚠️ La mobilité n'expose AUCUN filtre de prix : transmettre `price_max`
    // n'y ferait rien de visible. On l'omet plutôt que de laisser croire à un
    // filtrage qui n'a pas lieu.
    if (budget && this.selected() !== 'mobilite') {
      queryParams['price_max'] = budget;
    }

    void this.router.navigate(['/recherche'], { queryParams });
  }
}
