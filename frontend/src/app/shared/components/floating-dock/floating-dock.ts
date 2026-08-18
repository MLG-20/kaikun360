import { ChangeDetectionStrategy, Component } from '@angular/core';

import { AssistantLauncherComponent } from '../assistant/assistant-launcher';
import { WhatsappFabComponent } from '../whatsapp-fab/whatsapp-fab';

/**
 * Pile de bulles flottantes, coin bas-droite — un seul point de positionnement
 * fixe pour tout le coin, monté une fois par layout (public, espaces connectés,
 * back-office) à côté de `app-assistant-panel`.
 *
 * Pourquoi un conteneur plutôt que deux composants indépendamment en
 * `position: fixed` : un simple `flex-direction: column` empile Nancy et
 * WhatsApp dans l'ordre du DOM SANS calcul de décalage `bottom` fragile entre
 * les deux styles — et si WhatsApp est absent (aucun numéro paramétré), Nancy
 * reprend seule la place du bas automatiquement, sans code conditionnel ici.
 */
@Component({
  selector: 'app-floating-dock',
  imports: [AssistantLauncherComponent, WhatsappFabComponent],
  templateUrl: './floating-dock.html',
  styleUrl: './floating-dock.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FloatingDockComponent {}
