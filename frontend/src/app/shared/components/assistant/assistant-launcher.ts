import { ChangeDetectionStrategy, Component, inject } from '@angular/core';

import { AssistantStore } from '../../../core/state/assistant-store';

/**
 * Le **déclencheur** de l'assistant Kaikun 360, posé dans les en-têtes (F10.5).
 *
 * ── Pourquoi il existe ──────────────────────────────────────────────────────
 * L'assistant s'ouvrait auparavant depuis une bulle flottante dans le coin
 * bas-droite. Deux défauts : elle recouvrait le contenu de chaque page sans
 * qu'on le lui demande, et elle disputait ce coin à `app-scroll-top` et au
 * bandeau d'installation. Dans l'en-tête, l'assistant devient ce qu'il est —
 * une entrée du produit, à côté de « Mon espace » — et le coin se libère.
 *
 * ── Ce qu'il n'est pas ──────────────────────────────────────────────────────
 * Il n'ouvre rien lui-même : il bascule un état du `AssistantStore`, que le
 * tiroir (`AssistantPanelComponent`) observe. C'est ce qui permet de le poser
 * dans trois en-têtes différents — public, espaces connectés, back-office —
 * sans qu'aucun ne connaisse le tiroir ni ne le monte deux fois.
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
