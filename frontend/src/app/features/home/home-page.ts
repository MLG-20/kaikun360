import { ChangeDetectionStrategy, Component } from '@angular/core';

import { OrbitHeroComponent } from '../../shared/components/orbit-hero/orbit-hero';
import { SearchEngineComponent } from '../../shared/components/search-engine/search-engine';

/**
 * Page d'accueil publique (F2.2).
 *
 * C'est la vitrine principale de Kaikun 360 : la première page que voit un
 * visiteur. Elle raconte l'offre de haut en bas —
 *   1. un « hero » d'accroche (promesse + preuve de confiance + signature
 *      orbitale animée) posé sur le moteur de recherche global.
 *
 * Les sections suivantes (univers, protocole de confiance, vitrine du catalogue,
 * bandeaux thématiques, simulateur, statistiques) sont ajoutées au fil des
 * sous-phases F2.2.2 → F2.2.4.
 *
 * La page n'a presque pas de logique : elle assemble des composants réutilisables
 * (`app-orbit-hero`, `app-search-engine`) et affiche des contenus statiques de
 * présentation. Les données réelles (catalogue) arriveront via `CatalogService`
 * dans la vitrine (F2.2.3).
 */
@Component({
  selector: 'app-home-page',
  imports: [OrbitHeroComponent, SearchEngineComponent],
  templateUrl: './home-page.html',
  styleUrl: './home-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class HomePageComponent {
  /**
   * Bandeau de confiance affiché sous l'accroche : quelques repères chiffrés qui
   * rassurent immédiatement le visiteur (surtout la diaspora, méfiante des
   * arnaques). Ce sont des repères de présentation, pas des données temps réel.
   */
  protected readonly trust = [
    { value: '14', label: 'régions couvertes' },
    { value: '9', label: 'univers de services' },
    { value: '100 %', label: 'biens vérifiés' },
  ];
}
