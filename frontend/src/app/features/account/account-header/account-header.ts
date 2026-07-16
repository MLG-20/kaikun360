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

import { AuthService } from '../../../core/auth/auth.service';

/**
 * En-tête dédié de l'espace client (F3.1, choix UX de l'utilisateur).
 *
 * Volontairement **épuré** — pas les méga-menus « marketing » du site public :
 * dans son espace, la personne doit se sentir chez elle. On garde donc juste
 *
 *   - la **marque** (retour à l'accueil du site),
 *   - un lien discret **« Retour au site »**,
 *   - un **menu utilisateur** (avatar + nom → déconnexion ; « Mon profil »
 *     s'ajoutera avec l'écran Profil en F3.2).
 *
 * En petit écran, un **bouton hamburger** (à gauche) ouvre le tiroir de
 * navigation latérale géré par le layout : l'état `sidebarOpen` est fourni par
 * le parent et le bouton émet `sidebarToggle`.
 *
 * Le menu utilisateur se referme à la navigation, sur Échap, ou au clic en
 * dehors de l'en-tête (même mécanique que le header public).
 */
@Component({
  selector: 'app-account-header',
  imports: [RouterLink],
  templateUrl: './account-header.html',
  styleUrl: './account-header.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountHeaderComponent {
  private readonly host = inject(ElementRef<HTMLElement>);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  /** État du tiroir de navigation (fourni par le layout, pour l'aria du bouton). */
  readonly sidebarOpen = input(false);
  /** Demande d'ouverture/fermeture du tiroir latéral (mobile). */
  readonly sidebarToggle = output<void>();

  /** Utilisateur connecté (nom, e-mail/téléphone, initiale). */
  protected readonly user = this.auth.user;

  /** Initiale affichée dans la pastille avatar. */
  protected readonly initial = computed(() => (this.user()?.name ?? '?').trim()[0] ?? '?');

  /** État du menu utilisateur déroulant (distinct du tiroir latéral). */
  protected readonly userMenuOpen = signal(false);

  constructor() {
    // Toute navigation referme le menu utilisateur.
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
