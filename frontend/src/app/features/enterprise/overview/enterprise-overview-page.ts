import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';
import { RouterLink } from '@angular/router';

import { AuthService } from '../../../core/auth/auth.service';
import { AccountIconComponent } from '../../account/account-icon';
import { ENTERPRISE_NAV, ENTERPRISE_SPACE } from '../enterprise-space';

/**
 * Accueil de l'espace **entreprise** (F6) — « Tableau de bord ».
 *
 * Page d'atterrissage de l'espace : une salutation, un **appel à l'action
 * principal** (demander un pack groupe — le cœur du besoin entreprise, cahier
 * §9.4) et les **tuiles** vers les rubriques (source : `ENTERPRISE_NAV`). Aucun
 * appel réseau : tout est dérivé de l'utilisateur déjà en mémoire, comme
 * l'accueil de l'espace client.
 */
@Component({
  selector: 'app-enterprise-overview-page',
  imports: [RouterLink, AccountIconComponent],
  templateUrl: './enterprise-overview-page.html',
  styleUrl: './enterprise-overview-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class EnterpriseOverviewPageComponent {
  private readonly auth = inject(AuthService);

  /** Utilisateur connecté (pour la salutation). */
  protected readonly user = this.auth.user;

  /** Premier mot du nom, pour une salutation courte. */
  protected readonly firstName = computed(() => (this.user()?.name ?? '').split(' ')[0] || '');

  /** Rubriques de l'espace (tuiles). */
  protected readonly sections = ENTERPRISE_NAV;

  /** Préfixe d'URL de l'espace (pour construire les liens). */
  protected readonly base = ENTERPRISE_SPACE.basePath;
}
