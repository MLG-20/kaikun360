import { ChangeDetectionStrategy, Component, signal } from '@angular/core';

/**
 * En-tête global (F0.3). Logo + navigation des 5 univers + CTA connexion.
 *
 * Les liens de navigation sont des placeholders visuels pour l'instant : ils
 * seront routés au fur et à mesure des fonctionnalités (F2+). Le menu mobile est
 * piloté par un signal local.
 */
@Component({
  selector: 'app-header',
  templateUrl: './header.html',
  styleUrl: './header.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class HeaderComponent {
  /** Les cinq univers de la plateforme (vocabulaire final : Tourisme, Transport). */
  protected readonly universes = [
    'Immobilier',
    'Tourisme',
    'Transport',
    'Construction',
    'Services',
  ];

  protected readonly menuOpen = signal(false);

  protected toggleMenu(): void {
    this.menuOpen.update((open) => !open);
  }
}
