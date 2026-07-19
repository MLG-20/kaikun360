import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { filter } from 'rxjs/operators';

import { AuthService } from '../../core/auth/auth.service';
import { AccountIconComponent } from '../../features/account/account-icon';
import { SPACE_CONFIG } from './space.config';
import { SpaceHeaderComponent } from './space-header';

/**
 * Layout **générique** des espaces connectés (F4) : coquille app-shell partagée
 * par l'espace client, l'espace propriétaire et les futurs espaces pro.
 *
 * Rien n'y est propre à un espace : marque, sous-titre, rubriques de navigation
 * et cibles des liens transverses proviennent de `SPACE_CONFIG` (fourni au
 * niveau des routes de chaque espace). Le contenu de chaque rubrique est rendu
 * dans le `router-outlet`.
 *
 * Chrome validé (F3.6) et conservé : **menu latéral SOMBRE pleine hauteur** collé
 * à gauche (rail permanent en desktop), colonne contenu à droite (en-tête épuré
 * + section), **ni méga-menus ni pied de page** du site public. Sur petit écran
 * (< 860px), le rail devient un **tiroir** (bouton hamburger de l'en-tête →
 * `sidebarOpen`), refermé au clic sur le fond, sur un lien, ou à la navigation.
 *
 * La protection d'accès (authGuard / roleGuard) est posée sur les routes de
 * chaque espace : on n'arrive donc ici qu'avec une session (et le bon rôle).
 *
 * Généralise l'ancien `app-account-layout` (F3), désormais réutilisé tel quel.
 */
@Component({
  selector: 'app-space-layout',
  imports: [
    RouterOutlet,
    RouterLink,
    RouterLinkActive,
    SpaceHeaderComponent,
    AccountIconComponent,
  ],
  templateUrl: './space-layout.html',
  styleUrl: './space-layout.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class SpaceLayoutComponent {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  /** Configuration de l'espace courant (marque, nav, liens). */
  protected readonly space = inject(SPACE_CONFIG);

  /** Utilisateur connecté (nom + initiale, affiché en pied de menu). */
  protected readonly user = this.auth.user;

  /** Tiroir de navigation ouvert ? (petit écran uniquement ; rail permanent en desktop). */
  protected readonly sidebarOpen = signal(false);

  constructor() {
    // Toute navigation referme le tiroir (sans effet en desktop, où il est fixe).
    this.router.events
      .pipe(
        filter((e) => e instanceof NavigationEnd),
        takeUntilDestroyed(),
      )
      .subscribe(() => this.sidebarOpen.set(false));
  }

  /** Construit le `routerLink` d'une rubrique à partir de son chemin relatif. */
  protected linkFor(path: string): unknown[] {
    return path ? [this.space.basePath, path] : [this.space.basePath];
  }

  /** Ouvre/ferme le tiroir (déclenché par le hamburger de l'en-tête, petit écran). */
  protected toggleSidebar(): void {
    this.sidebarOpen.update((open) => !open);
  }

  /** Ferme le tiroir (clic sur le fond ou sur un lien, petit écran). */
  protected closeSidebar(): void {
    this.sidebarOpen.set(false);
  }

  /** Déconnexion depuis le pied du menu latéral : vide la session, retour à l'accueil. */
  protected logout(): void {
    this.auth.logout().subscribe({
      next: () => this.router.navigate(['/']),
      error: () => this.router.navigate(['/']),
    });
  }
}
