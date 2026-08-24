import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { ManageService } from '../../../core/api/manage.service';
import { AuthService } from '../../../core/auth/auth.service';
import { AccountIconComponent } from '../../account/account-icon';
import { OwnerDashboard } from '../../../models/manage.model';
import { OWNER_NAV } from '../owner-space';

/** Une tuile d'indicateur du tableau de bord (valeur formatée + libellé). */
interface OwnerStat {
  /** Intitulé de l'indicateur. */
  label: string;
  /** Valeur déjà formatée pour l'affichage (montant FCFA ou compteur). */
  value: string;
  /**
   * Tonalité visuelle : `warn` met en avant un point d'attention (loyers
   * impayés, incidents ouverts) quand la valeur n'est pas nulle.
   */
  tone: 'neutral' | 'warn' | 'good';
}

/**
 * Accueil de l'**espace propriétaire** (F4.1) — tableau de bord de gestion
 * locative.
 *
 * Contrairement à l'accueil client (purement dérivé de la session, sans réseau),
 * cet écran interroge `GET /manage/dashboard` (service `ManageService`) pour
 * afficher les **agrégats réels** du propriétaire connecté : mandats actifs,
 * loyers encaissés / impayés, dépenses, commission Kaikun, reversements,
 * incidents ouverts —
 * scopés côté backend aux seuls biens du propriétaire. Les KPI riches sont
 * assumés ici (espace pro), à la différence de l'accueil client.
 *
 * Les **tuiles de sections** (Mes biens, Gestion locative, Documents) reprennent
 * `OWNER_NAV` : celles non encore construites sont marquées « Bientôt ».
 */
@Component({
  selector: 'app-owner-overview-page',
  imports: [RouterLink, AccountIconComponent],
  templateUrl: './owner-overview-page.html',
  styleUrl: './owner-overview-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class OwnerOverviewPageComponent {
  private readonly auth = inject(AuthService);
  private readonly manage = inject(ManageService);

  /** Utilisateur connecté (propriétaire). */
  protected readonly user = this.auth.user;

  /** Prénom (premier mot du nom) pour une salutation courte. */
  protected readonly firstName = computed(() => (this.user()?.name ?? '').split(' ')[0] || '');

  /** Chargement du tableau de bord en cours. */
  protected readonly loading = signal(true);
  /** Le chargement a échoué (réseau/serveur). */
  protected readonly loadError = signal(false);
  /** Agrégats renvoyés par l'API (null tant que non chargés). */
  protected readonly dash = signal<OwnerDashboard | null>(null);

  /** Tuiles de sections (toutes les rubriques sauf l'accueil). */
  protected readonly sections = OWNER_NAV.filter((item) => item.path !== '');

  /** Indicateurs dérivés du tableau de bord, prêts à afficher. */
  protected readonly stats = computed<OwnerStat[]>(() => {
    const d = this.dash();
    if (!d) {
      return [];
    }
    return [
      { label: 'Mandats actifs', value: this.count(d.mandats_actifs), tone: 'neutral' },
      { label: 'Loyers encaissés', value: this.fcfa(d.loyers_payes_xof), tone: 'good' },
      {
        label: 'Loyers impayés',
        value: this.fcfa(d.loyers_impayes_xof),
        tone: d.loyers_impayes_xof > 0 ? 'warn' : 'neutral',
      },
      { label: 'Dépenses', value: this.fcfa(d.depenses_xof), tone: 'neutral' },
      { label: 'Commission Kaikun', value: this.fcfa(d.commission_xof), tone: 'neutral' },
      { label: 'Reversements', value: this.fcfa(d.reversements_xof), tone: 'neutral' },
      {
        label: 'Incidents ouverts',
        value: this.count(d.incidents_ouverts),
        tone: d.incidents_ouverts > 0 ? 'warn' : 'neutral',
      },
    ];
  });

  constructor() {
    this.load();
  }

  /**
   * Charge le tableau de bord. Guardé par la présence d'une session : côté
   * serveur (SSR) le jeton en mémoire est absent, l'appel serait voué au 401 ;
   * la route est de toute façon protégée par le rôle propriétaire.
   */
  protected load(): void {
    if (!this.user()) {
      this.loading.set(false);
      return;
    }
    this.loading.set(true);
    this.loadError.set(false);
    this.manage.dashboard().subscribe({
      next: (res) => {
        this.dash.set(res.data);
        this.loading.set(false);
      },
      error: () => {
        this.loadError.set(true);
        this.loading.set(false);
      },
    });
  }

  /** Formate un montant FCFA (« 150 000 F »). */
  private fcfa(value: number): string {
    return `${value.toLocaleString('fr-FR').replace(/ /g, ' ')} F`;
  }

  /** Formate un compteur simple (entier). */
  private count(value: number): string {
    return value.toLocaleString('fr-FR');
  }
}
