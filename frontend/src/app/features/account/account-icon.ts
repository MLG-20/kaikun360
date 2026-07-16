import { ChangeDetectionStrategy, Component, input } from '@angular/core';

import { AccountIcon } from './account-nav';

/**
 * Petite icône SVG de l'espace client (F3.1).
 *
 * Mutualise le rendu des pictogrammes entre la navigation latérale
 * (`account-layout`) et les tuiles de l'accueil de l'espace, à partir d'une clé
 * `AccountIcon`. Les tracés sont en `currentColor` (héritent de la couleur du
 * texte), sans dépendance externe — cohérent avec l'approche du header.
 */
@Component({
  selector: 'app-account-icon',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      @switch (name()) {
        @case ('grid') {
          <rect x="3" y="3" width="7" height="7" rx="1.5" />
          <rect x="14" y="3" width="7" height="7" rx="1.5" />
          <rect x="3" y="14" width="7" height="7" rx="1.5" />
          <rect x="14" y="14" width="7" height="7" rx="1.5" />
        }
        @case ('inbox') {
          <path d="M3 12h5l2 3h4l2-3h5" />
          <path d="M5 5h14l2 7v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5z" />
        }
        @case ('calendar') {
          <rect x="3" y="4" width="18" height="17" rx="2" />
          <path d="M3 9h18M8 2v4M16 2v4" />
        }
        @case ('heart') {
          <path d="M12 20s-7-4.35-9.5-8.5C1 8 3 4.5 6.5 4.5c2 0 3.5 1.5 5.5 3.5 2-2 3.5-3.5 5.5-3.5C21 4.5 23 8 21.5 11.5 19 15.65 12 20 12 20z" />
        }
        @case ('bell') {
          <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
          <path d="M10.5 21a1.8 1.8 0 0 0 3 0" />
        }
        @case ('chat') {
          <path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
        }
        @case ('user') {
          <circle cx="12" cy="8" r="4" />
          <path d="M4 21c0-4 3.5-6 8-6s8 2 8 6" />
        }
      }
    </svg>
  `,
  styles: `
    :host { display: inline-flex; }
    svg { width: 100%; height: 100%; }
  `,
})
export class AccountIconComponent {
  /** Clé d'icône à afficher. */
  readonly name = input.required<AccountIcon>();
}
