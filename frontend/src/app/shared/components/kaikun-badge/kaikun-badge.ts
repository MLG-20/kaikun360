import { ChangeDetectionStrategy, Component } from '@angular/core';

/**
 * Étiquette « Kaikun 360 » — signale une offre déposée et publiée par l'équipe
 * elle-même plutôt que par un prestataire ou un propriétaire (F21.1).
 *
 * Purement présentielle : la page hôte décide de l'afficher d'après le champ
 * `published_by_kaikun` renvoyé par l'API.
 */
@Component({
  selector: 'app-kaikun-badge',
  template: `<span class="kaikun-badge" title="Offre publiée par l'équipe Kaikun 360">Kaikun 360</span>`,
  styleUrl: './kaikun-badge.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class KaikunBadgeComponent {}
