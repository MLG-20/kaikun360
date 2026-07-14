import { ChangeDetectionStrategy, Component, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

/** Entrée de la méga-navigation : libellé + route (null = pas encore routé). */
interface NavUniverse {
  label: string;
  link: string | null;
}

/**
 * En-tête global (F0.3, liens câblés en F1 puis F2).
 *
 * La marque et le bouton « Connexion » sont routés (RouterLink). Les univers
 * pointent vers leur page dédiée dès qu'elle existe (`link`) ; les autres
 * restent des placeholders visuels jusqu'à leur phase (F2.4/F2.5). Le menu
 * mobile est piloté par un signal local.
 */
@Component({
  selector: 'app-header',
  imports: [RouterLink],
  templateUrl: './header.html',
  styleUrl: './header.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class HeaderComponent {
  /** Les univers mis en avant dans la méga-nav (Immobilier/Nuitées routés en F2.3). */
  protected readonly universes: NavUniverse[] = [
    { label: 'Immobilier', link: '/immobilier' },
    { label: 'Nuitées', link: '/nuitees' },
    { label: 'Tourisme', link: null },
    { label: 'Transport', link: null },
    { label: 'Construction', link: null },
    { label: 'Services', link: null },
  ];

  protected readonly menuOpen = signal(false);

  protected toggleMenu(): void {
    this.menuOpen.update((open) => !open);
  }
}
