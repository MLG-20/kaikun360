import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';

import { AuthService } from '../../core/auth/auth.service';
import { AccountIconComponent } from '../../features/account/account-icon';
import { ACCOUNT_NAV } from '../../features/account/account-nav';
import { FooterComponent } from '../../shared/components/footer/footer';
import { HeaderComponent } from '../../shared/components/header/header';

/**
 * Layout de l'espace client (F3.1) : coquille des pages authentifiées.
 *
 * Réutilise l'en-tête et le pied de page du site, et ajoute une **navigation
 * latérale** listant les sections de l'espace personnel (source : `ACCOUNT_NAV`).
 * Le contenu de chaque section est rendu dans le `router-outlet`.
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
    HeaderComponent,
    FooterComponent,
    AccountIconComponent,
  ],
  templateUrl: './account-layout.html',
  styleUrl: './account-layout.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountLayoutComponent {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  /** Sections de l'espace (prêtes ou « bientôt »), pour la navigation latérale. */
  protected readonly nav = ACCOUNT_NAV;

  /** Utilisateur connecté (pour l'entête de la barre latérale). */
  protected readonly user = this.auth.user;

  /** Déconnexion : révoque le jeton puis renvoie vers l'accueil public. */
  protected logout(): void {
    this.auth.logout().subscribe({
      // La session locale est vidée dans tous les cas (voir AuthService) ;
      // on redirige vers l'accueil quel que soit le résultat de l'appel.
      next: () => this.router.navigate(['/']),
      error: () => this.router.navigate(['/']),
    });
  }
}
