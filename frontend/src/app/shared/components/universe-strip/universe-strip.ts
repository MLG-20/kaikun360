import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';

import { UniverseStripService } from '../../../core/api/universe-strip.service';

/**
 * Bande défilante des univers, posée juste sous le héros de l'accueil
 * (F16.2). Juste des noms qui glissent en continu — pas une nouvelle grille
 * de tuiles (déjà là, section 2) : un repère visuel « on couvre tout ça »,
 * lu en un coup d'œil avant même de dérouler la page.
 *
 * Composant à part, avec SA PROPRE feuille de style : `home-page.scss` est
 * déjà au maximum de son budget de build (F13.5/F16.1), un composant neuf
 * repart avec un budget vierge plutôt que de faire déborder l'existant.
 */
@Component({
  selector: 'app-universe-strip',
  imports: [],
  templateUrl: './universe-strip.html',
  styleUrl: './universe-strip.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UniverseStripComponent {
  private readonly strip = inject(UniverseStripService);

  /** Noms publiés (l'équipe peut en masquer certains au back-office). */
  protected readonly names = toSignal(this.strip.list(), { initialValue: [] as string[] });
}
