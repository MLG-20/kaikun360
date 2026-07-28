import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { filter } from 'rxjs/operators';

import { AuthService } from '../../core/auth/auth.service';

/** Clé d'icône du rail (rendue en SVG inline dans le template). */
type BoIcon =
  | 'grid'
  | 'check'
  | 'layers'
  | 'calendar'
  | 'card'
  | 'folder'
  | 'id'
  | 'star'
  | 'users'
  | 'shield'
  | 'clock';

/** Une rubrique de navigation du poste de commandement. */
interface BoNavItem {
  label: string;
  /** Chemin relatif à `/back-office` (`''` = accueil). */
  path: string;
  icon: BoIcon;
  /** L'écran est-il construit ? (sinon « Bientôt », non cliquable). */
  ready: boolean;
}

/**
 * Shell **dédié et indépendant** du back-office (F7.1.e).
 *
 * ⚠️ Ce n'est PAS le `SpaceLayoutComponent` des espaces utilisateurs : décision
 * produit d'un habillage séparé (« salle de commande ») pour bien distinguer le
 * poste de commandement — là où « tout passe » — des espaces clients/pro, et
 * pour lui appliquer un niveau de sécurité propre (guard de rôle strict sur la
 * route racine, session courte + 2FA côté back).
 *
 * Structure : rail sombre « graphite » à gauche (marque + navigation + pied
 * utilisateur/déconnexion), colonne contenu à droite (barre supérieure + outlet).
 * Sur petit écran, le rail devient un tiroir (hamburger de la barre).
 */
@Component({
  selector: 'app-backoffice-layout',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  templateUrl: './backoffice-layout.html',
  styleUrl: './backoffice-layout.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeLayoutComponent {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  /** Utilisateur connecté (nom, initiale, rôle affichés). */
  protected readonly user = this.auth.user;

  /** Libellé du rôle back-office principal (pour la barre + le pied de rail). */
  protected readonly roleLabel = computed(() => {
    const roles = this.user()?.roles ?? [];
    if (roles.includes('super_admin')) return 'Super administrateur';
    if (roles.includes('admin')) return 'Administrateur';
    if (roles.includes('agent_kaikun')) return 'Agent';
    return 'Équipe';
  });

  /** Rubriques du poste de commandement (F7.1 : Vue d'ensemble → Pointeuse ; F7.2 : Validation, Catalogues). */
  protected readonly nav: readonly BoNavItem[] = [
    { label: 'Vue d’ensemble', path: '', icon: 'grid', ready: true },
    { label: 'Validation', path: 'validation', icon: 'check', ready: true },
    { label: 'Catalogues', path: 'catalogues', icon: 'layers', ready: true },
    { label: 'Nuitées', path: 'nuitees', icon: 'calendar', ready: true },
    { label: 'Paiements', path: 'paiements', icon: 'card', ready: true },
    { label: 'Dossiers', path: 'dossiers', icon: 'folder', ready: true },
    { label: 'Comptes', path: 'comptes', icon: 'id', ready: true },
    { label: 'Avis & qualité', path: 'qualite', icon: 'star', ready: true },
    { label: 'Équipe', path: 'equipe', icon: 'users', ready: true },
    { label: 'Permissions', path: 'permissions', icon: 'shield', ready: true },
    { label: 'Pointeuse', path: 'pointeuse', icon: 'clock', ready: true },
  ];

  /** Tiroir ouvert ? (petit écran uniquement ; rail permanent en desktop). */
  protected readonly sidebarOpen = signal(false);

  constructor() {
    this.router.events
      .pipe(
        filter((e) => e instanceof NavigationEnd),
        takeUntilDestroyed(),
      )
      .subscribe(() => this.sidebarOpen.set(false));
  }

  /** `routerLink` d'une rubrique à partir de son chemin relatif. */
  protected linkFor(path: string): unknown[] {
    return path ? ['/back-office', path] : ['/back-office'];
  }

  protected toggleSidebar(): void {
    this.sidebarOpen.update((open) => !open);
  }

  protected closeSidebar(): void {
    this.sidebarOpen.set(false);
  }

  /** Déconnexion : révoque le jeton puis renvoie vers la connexion. */
  protected logout(): void {
    this.auth.logout().subscribe({
      next: () => void this.router.navigateByUrl('/auth/connexion'),
      error: () => void this.router.navigateByUrl('/auth/connexion'),
    });
  }
}
