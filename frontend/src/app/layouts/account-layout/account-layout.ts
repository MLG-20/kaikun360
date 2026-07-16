import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';

import { AccountHeaderComponent } from '../../features/account/account-header/account-header';
import { AccountIconComponent } from '../../features/account/account-icon';
import { ACCOUNT_NAV } from '../../features/account/account-nav';
import { FooterComponent } from '../../shared/components/footer/footer';

/**
 * Layout de l'espace client (F3.1) : coquille des pages authentifiées.
 *
 * Utilise un **en-tête dédié** (`app-account-header`, épuré, sans les méga-menus
 * du site public) et une **navigation latérale** listant les sections de
 * l'espace personnel (source : `ACCOUNT_NAV`). Le contenu de chaque section est
 * rendu dans le `router-outlet`. L'identité et la déconnexion vivent dans
 * l'en-tête ; la barre latérale ne fait que la navigation entre rubriques.
 *
 * Toute la branche `/mon-espace` est protégée par `authGuard` (voir
 * `account.routes.ts`) : on n'arrive donc ici qu'avec une session active.
 */
@Component({
  selector: 'app-account-layout',
  imports: [
    RouterOutlet,
    RouterLink,
    RouterLinkActive,
    AccountHeaderComponent,
    FooterComponent,
    AccountIconComponent,
  ],
  templateUrl: './account-layout.html',
  styleUrl: './account-layout.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountLayoutComponent {
  /** Sections de l'espace (prêtes ou « bientôt »), pour la navigation latérale. */
  protected readonly nav = ACCOUNT_NAV;
}
