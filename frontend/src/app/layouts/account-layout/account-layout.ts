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
 * connectés, pour que la personne se sente chez elle. La barre latérale est un
 * **tiroir qui pousse le contenu** (bouton hamburger de l'en-tête →
 * `sidebarOpen`), collé au bord gauche, sous l'en-tête. Même comportement à
 * toutes les tailles, seul l'**état par défaut** change : **ouvert et épinglé
 * sur desktop** (on a la place), **fermé sur petit écran** (où il glisse par
 * dessus avec un fond assombri). Sur petit écran, il se referme au clic sur le
 * fond, sur un lien ou à la navigation ; sur desktop il reste épinglé.
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
  /** Largeur (px) à partir de laquelle on est en « desktop » (miroir du SCSS). */
  private static readonly DESKTOP_MIN = 861;

  /** Sections de l'espace (prêtes ou « bientôt »), pour la navigation latérale. */
  protected readonly nav = ACCOUNT_NAV;

  /**
   * Tiroir de navigation ouvert ? **Ouvert par défaut sur desktop** (épinglé,
   * on a la place), **fermé sur petit écran**. Côté serveur (pas de `window`),
   * on assume desktop — de toute façon la branche est auth-gated et ne rend pas
   * réellement en SSR (jeton en mémoire seule).
   */
  protected readonly sidebarOpen = signal(this.isDesktop());

  constructor() {
    // Sur PETIT ÉCRAN uniquement, toute navigation referme le tiroir (il glisse
    // par-dessus le contenu). Sur desktop il est épinglé : on le laisse ouvert.
    inject(Router)
      .events.pipe(
        filter((e) => e instanceof NavigationEnd),
        takeUntilDestroyed(),
      )
      .subscribe(() => {
        if (!this.isDesktop()) {
          this.sidebarOpen.set(false);
        }
      });
  }

  /** Ouvre/ferme le tiroir (déclenché par le hamburger de l'en-tête). */
  protected toggleSidebar(): void {
    this.sidebarOpen.update((open) => !open);
  }

  /**
   * Ferme le tiroir au clic sur le fond ou sur un lien — mais **seulement sur
   * petit écran** : sur desktop le menu est épinglé et ne se referme pas en
   * naviguant.
   */
  protected closeSidebar(): void {
    if (!this.isDesktop()) {
      this.sidebarOpen.set(false);
    }
  }

  /** Sommes-nous en affichage desktop ? (fenêtre large ; vrai par défaut en SSR). */
  private isDesktop(): boolean {
    return typeof window === 'undefined' || window.innerWidth >= AccountLayoutComponent.DESKTOP_MIN;
  }
}
