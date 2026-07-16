import {
  ChangeDetectionStrategy,
  Component,
  ElementRef,
  HostListener,
  computed,
  inject,
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
 * La navigation ENTRE les rubriques de l'espace reste dans la barre latérale
 * (`account-layout`). Le menu déroulant se referme à la navigation, sur Échap,
 * ou au clic en dehors de l'en-tête (même mécanique que le header public).
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

  /** Utilisateur connecté (nom, e-mail/téléphone, initiale). */
  protected readonly user = this.auth.user;

  /** Initiale affichée dans la pastille avatar. */
  protected readonly initial = computed(() => (this.user()?.name ?? '?').trim()[0] ?? '?');

  /** État du menu utilisateur déroulant. */
  protected readonly menuOpen = signal(false);

  constructor() {
    // Toute navigation referme le menu utilisateur.
    this.router.events
      .pipe(
        filter((e) => e instanceof NavigationEnd),
        takeUntilDestroyed(),
      )
      .subscribe(() => this.menuOpen.set(false));
  }

  /** Ouvre/ferme le menu utilisateur. */
  protected toggleMenu(): void {
    this.menuOpen.update((open) => !open);
  }

  /** Déconnexion : vide la session puis renvoie à l'accueil du site. */
  protected logout(): void {
    this.menuOpen.set(false);
    this.auth.logout().subscribe({
      next: () => this.router.navigate(['/']),
      error: () => this.router.navigate(['/']),
    });
  }

  /** Échap referme le menu. */
  @HostListener('document:keydown.escape')
  protected onEscape(): void {
    this.menuOpen.set(false);
  }

  /** Un clic en dehors de l'en-tête referme le menu ouvert. */
  @HostListener('document:click', ['$event'])
  protected onDocumentClick(event: MouseEvent): void {
    if (this.menuOpen() && !this.host.nativeElement.contains(event.target)) {
      this.menuOpen.set(false);
    }
  }
}
