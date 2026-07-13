import { ChangeDetectionStrategy, Component, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

/**
 * En-tête global (F0.3, liens câblés en F1).
 *
 * La marque et le bouton « Connexion » sont routés (RouterLink). Les liens des
 * univers restent des placeholders visuels tant que leurs pages n'existent pas
 * (routées en F2+). Le menu mobile est piloté par un signal local.
 */
@Component({
  selector: 'app-header',
  imports: [RouterLink],
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
