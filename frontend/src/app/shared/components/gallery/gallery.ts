import { ChangeDetectionStrategy, Component, computed, input, signal } from '@angular/core';

/**
 * Galerie photo (F0.4) — image principale + bande de miniatures cliquables.
 *
 * Reçoit une liste d'URLs (`images`) ; la sélection est gérée par un signal.
 * Utilisée sur les pages de détail (bien, nuitée, expérience…), alimentée par
 * l'API Médias.
 */
@Component({
  selector: 'app-gallery',
  templateUrl: './gallery.html',
  styleUrl: './gallery.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class GalleryComponent {
  readonly images = input.required<string[]>();
  readonly alt = input('Photo');

  protected readonly selected = signal(0);

  /** URL de l'image affichée en grand (ou null si la liste est vide). */
  protected readonly current = computed<string | null>(() => this.images()[this.selected()] ?? null);

  protected select(index: number): void {
    this.selected.set(index);
  }
}
