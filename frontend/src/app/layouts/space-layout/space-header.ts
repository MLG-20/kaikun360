import {
  ChangeDetectionStrategy,
  Component,
  ElementRef,
  HostListener,
  computed,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink } from '@angular/router';
import { filter } from 'rxjs/operators';

import { AuthService } from '../../core/auth/auth.service';
import { UnreadStore } from '../../core/state/unread-store';
import { AssistantLauncherComponent } from '../../shared/components/assistant/assistant-launcher';
import { SPACE_CONFIG } from './space.config';

/**
 * Barre supérieure **générique** d'un espace connecté (F4) : occupe la colonne
 * contenu, à droite du rail latéral sombre (la marque et la déconnexion vivent
 * dans le rail, géré par `SpaceLayoutComponent`). Volontairement épurée :
 *
 *   - le **titre** de l'espace (fourni par `SPACE_CONFIG.headerTitle`) ;
 *   - la **cloche de notifications** (F3.6) avec sa pastille de non-lues ;
 *   - un **menu utilisateur** (avatar + nom → « Mon profil » et déconnexion).
 *
 * Rien n'est propre à l'espace client : les cibles des liens (cloche, profil)
 * proviennent de la config. En petit écran, un **bouton hamburger** ouvre le
 * tiroir de navigation géré par le layout (`sidebarOpen` fourni par le parent,
 * `sidebarToggle` émis vers lui). Le menu utilisateur se referme à la
 * navigation, sur Échap, ou au clic en dehors de l'en-tête.
 *
 * Généralise l'ancien `app-account-header` (F3), désormais partagé par tous les
 * espaces (client, propriétaire, …).
 */
@Component({
  selector: 'app-space-header',
  imports: [RouterLink, AssistantLauncherComponent],
  templateUrl: './space-header.html',
  styleUrl: './space-header.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class SpaceHeaderComponent {
  private readonly host = inject(ElementRef<HTMLElement>);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly unread = inject(UnreadStore);

  /** Configuration de l'espace courant (titre, cibles des liens transverses). */
  protected readonly space = inject(SPACE_CONFIG);

  /** État du tiroir de navigation (fourni par le layout, pour l'aria du bouton). */
  readonly sidebarOpen = input(false);
  /** Demande d'ouverture/fermeture du tiroir latéral (mobile). */
  readonly sidebarToggle = output<void>();

  /** Utilisateur connecté (nom, e-mail/téléphone, initiale). */
  protected readonly user = this.auth.user;

  /** Initiale affichée dans la pastille avatar, à défaut d'image. */
  protected readonly initial = computed(() => (this.user()?.name ?? '?').trim()[0] ?? '?');

  /**
   * Photo de profil / logo d'entreprise (F8.0), `null` tant qu'aucune image
   * n'a été déposée — la pastille retombe alors sur l'initiale.
   *
   * Lu sur `AuthService.user`, que la page « Mon profil » met à jour après un
   * dépôt : l'en-tête reflète donc la nouvelle image immédiatement, sans
   * rechargement.
   */
  protected readonly avatarUrl = computed(() => this.user()?.profile?.avatar_url ?? null);

  /** `logo` pour une entreprise : l'image se pose dans un cadre, pas en rond. */
  protected readonly avatarIsLogo = computed(
    () => this.user()?.profile?.avatar_kind === 'logo',
  );

  /** État du menu utilisateur déroulant (distinct du tiroir latéral). */
  protected readonly userMenuOpen = signal(false);

  /**
   * Nombre de notifications non lues (pastille sur la cloche, F3.6).
   *
   * ⚠️ Ce compteur était **compté ici**, en local, et ne se rafraîchissait qu'à
   * la navigation : rien n'arrivait tant qu'on ne cliquait pas, et l'écran
   * « Notifications » ne pouvait pas éteindre la pastille qu'il venait de vider.
   * Il vient désormais de l'état partagé, qui se réveille aussi tout seul —
   * voir `UnreadStore` (F8.13).
   */
  protected readonly unreadCount = this.unread.notifications;

  constructor() {
    // Toute navigation referme le menu utilisateur. (Le rafraîchissement des
    // compteurs, lui, n'est plus l'affaire de l'en-tête : `UnreadStore` écoute
    // la navigation pour tout le monde.)
    this.router.events
      .pipe(
        filter((e) => e instanceof NavigationEnd),
        takeUntilDestroyed(),
      )
      .subscribe(() => this.userMenuOpen.set(false));
  }

  /** Ouvre/ferme le menu utilisateur. */
  protected toggleUserMenu(): void {
    this.userMenuOpen.update((open) => !open);
  }

  /** Déconnexion : vide la session puis renvoie à l'accueil du site. */
  protected logout(): void {
    this.userMenuOpen.set(false);
    this.auth.logout().subscribe({
      next: () => this.router.navigate(['/']),
      error: () => this.router.navigate(['/']),
    });
  }

  /** Échap referme le menu utilisateur. */
  @HostListener('document:keydown.escape')
  protected onEscape(): void {
    this.userMenuOpen.set(false);
  }

  /** Un clic en dehors de l'en-tête referme le menu utilisateur ouvert. */
  @HostListener('document:click', ['$event'])
  protected onDocumentClick(event: MouseEvent): void {
    if (this.userMenuOpen() && !this.host.nativeElement.contains(event.target)) {
      this.userMenuOpen.set(false);
    }
  }
}
