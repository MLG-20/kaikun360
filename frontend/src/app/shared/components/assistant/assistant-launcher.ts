import { ChangeDetectionStrategy, Component, inject } from '@angular/core';

import { AssistantStore } from '../../../core/state/assistant-store';

/**
 * Le **déclencheur** de l'assistant Kaikun 360 : une bulle flottante, coin
 * bas-droite, montée par chaque layout (F10.6).
 *
 * ── Historique ──────────────────────────────────────────────────────────
 * F10.1 : bulle flottante. F10.5 : déplacée dans les en-têtes (elle recouvrait
 * le contenu sans qu'on le lui demande, et disputait le coin à
 * `app-scroll-top`). F10.6 : retour à la bulle flottante, à la demande —
 * `app-scroll-top` est devenu un lien statique dans `app-footer`, le coin
 * bas-droite n'appartient donc plus qu'à cette bulle.
 *
 * ── Ce qu'il n'est pas ──────────────────────────────────────────────────────
 * Il n'ouvre rien lui-même : il bascule un état du `AssistantStore`, que le
 * tiroir (`AssistantPanelComponent`) observe. C'est ce qui permet de le poser
 * dans les layouts — public, espaces connectés, back-office — sans qu'aucun
 * ne connaisse le tiroir ni ne le monte deux fois.
 */
@Component({
  selector: 'app-assistant-launcher',
  templateUrl: './assistant-launcher.html',
  styleUrl: './assistant-launcher.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AssistantLauncherComponent {
  private readonly store = inject(AssistantStore);

  protected readonly ouvert = this.store.estOuvert;
  protected readonly indisponible = this.store.indisponible;

  protected basculer(): void {
    this.store.basculer();
  }
}
