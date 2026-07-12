import { ChangeDetectionStrategy, Component, input } from '@angular/core';

/**
 * Badge de vérification (F0.4) — pastille avec une coche, pour signaler une
 * ressource contrôlée (« Vérifié », « Vérifié notaire », « Suivi filmé »…).
 *
 * Deux tons : `default` (fond blanc, texte navy) ou `gold` (accent premium).
 */
@Component({
  selector: 'app-verification-badge',
  templateUrl: './verification-badge.html',
  styleUrl: './verification-badge.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class VerificationBadgeComponent {
  /** Texte affiché à côté de la coche. */
  readonly label = input('Vérifié');

  /** Variante visuelle. */
  readonly tone = input<'default' | 'gold'>('default');
}
