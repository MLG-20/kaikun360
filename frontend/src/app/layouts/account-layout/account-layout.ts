import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import {
  NavigationEnd,
  Router,
  RouterLink,
  RouterLinkActive,
  RouterOutlet,
} from '@angular/router';
import { filter } from 'rxjs/operators';

import { AccountHeaderComponent } from '../../features/account/account-header/account-header';
import { AccountIconComponent } from '../../features/account/account-icon';
import { ACCOUNT_NAV } from '../../features/account/account-nav';

/**
 * Layout de l'espace client (F3.1) : coquille des pages authentifiées.
 *
 * Utilise un **en-tête dédié** (`app-account-header`, épuré, sans les méga-menus
 * du site public) et une **navigation latérale** listant les sections de
 * l'espace personnel (source : `ACCOUNT_NAV`). Le contenu de chaque section est
 * rendu dans le `router-outlet`.
 *
 * Choix UX de l'utilisateur : **ni méga-menus ni pied de page** dans les espaces
 * connectés, pour que la personne se sente chez elle. En **petit écran**, la
 * barre latérale devient un **tiroir qui s'ouvre depuis la gauche** (bouton
 * hamburger de l'en-tête → `sidebarOpen`), avec un fond assombri ; elle se
 * referme au clic sur le fond, sur un lien, ou à la navigation.
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
    AccountIconComponent,
  ],
  templateUrl: './account-layout.html',
  styleUrl: './account-layout.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountLayoutComponent {
  /** Sections de l'espace (prêtes ou « bientôt »), pour la navigation latérale. */
  protected readonly nav = ACCOUNT_NAV;

  /** Tiroir de navigation ouvert ? (petit écran uniquement). */
  protected readonly sidebarOpen = signal(false);

  constructor() {
    // Toute navigation referme le tiroir (petit écran).
    inject(Router)
      .events.pipe(
        filter((e) => e instanceof NavigationEnd),
        takeUntilDestroyed(),
      )
      .subscribe(() => this.sidebarOpen.set(false));
  }

  /** Ouvre/ferme le tiroir (déclenché par le hamburger de l'en-tête). */
  protected toggleSidebar(): void {
    this.sidebarOpen.update((open) => !open);
  }

  /** Ferme le tiroir (clic sur le fond ou sur un lien). */
  protected closeSidebar(): void {
    this.sidebarOpen.set(false);
  }
}
