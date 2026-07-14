import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { RouterLink } from '@angular/router';

import { VerificationBadgeComponent } from '../verification-badge/verification-badge';

/**
 * Carte de bien / service (F0.4) — brique du catalogue, réutilisable pour tous
 * les univers (biens, nuitées, véhicules, expériences…).
 *
 * Générique et « présentielle » : pilotée par ses `input()`, sans logique métier.
 * En l'absence d'image, un dégradé de marque sert de vignette de repli.
 */
@Component({
  selector: 'app-listing-card',
  imports: [VerificationBadgeComponent, RouterLink],
  templateUrl: './listing-card.html',
  styleUrl: './listing-card.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ListingCardComponent {
  readonly title = input.required<string>();
  readonly location = input<string | null>(null);
  /** Prix formaté, ex. « 150 000 F ». */
  readonly price = input<string | null>(null);
  /** Unité, ex. « / mois », « / nuit ». */
  readonly priceUnit = input<string | null>(null);
  /** Libellé de badge de vérification (masqué si null). */
  readonly badge = input<string | null>(null);
  readonly cta = input('Découvrir');
  /** URL d'image ; si absente, une vignette dégradée est utilisée. */
  readonly image = input<string | null>(null);
  /**
   * Cible `routerLink` de la fiche détaillée (ex. `['/immobilier', 12]`).
   * Si `null`, la carte n'est pas cliquable (CTA neutralisé).
   */
  readonly link = input<(string | number)[] | null>(null);
}
