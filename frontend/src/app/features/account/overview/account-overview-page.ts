import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';
import { RouterLink } from '@angular/router';

import { AuthService } from '../../../core/auth/auth.service';
import { AccountIconComponent } from '../account-icon';
import { ACCOUNT_NAV } from '../account-nav';

/**
 * Accueil de l'espace client (F3.1) — « Tableau de bord ».
 *
 * Première page de l'espace personnel : salutation, état de vérification du
 * compte (invite à vérifier si besoin), et **tuiles vers les sections** de
 * l'espace (source : `ACCOUNT_NAV`, l'accueil lui-même exclu). Les tuiles des
 * sections non encore construites sont affichées « Bientôt » (non cliquables),
 * sans lien mort. Les sections se rempliront de données réelles en F3.2 → F3.7.
 */
@Component({
  selector: 'app-account-overview-page',
  imports: [RouterLink, AccountIconComponent],
  templateUrl: './account-overview-page.html',
  styleUrl: './account-overview-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountOverviewPageComponent {
  private readonly auth = inject(AuthService);

  /** Utilisateur connecté. */
  protected readonly user = this.auth.user;

  /** Prénom (premier mot du nom) pour une salutation courte. */
  protected readonly firstName = computed(() => (this.user()?.name ?? '').split(' ')[0] || '');

  /**
   * Compte vérifié dès qu'un canal (e-mail OU téléphone) est confirmé — même
   * règle que les formulaires auth-gated (F2.7). Sert à afficher l'invite de
   * vérification.
   */
  protected readonly isVerified = computed(() => {
    const u = this.user();
    return !!(u?.email_verified_at || u?.phone_verified_at);
  });

  /** Tuiles = toutes les sections sauf l'accueil (path vide). */
  protected readonly sections = ACCOUNT_NAV.filter((item) => item.path !== '');
}
