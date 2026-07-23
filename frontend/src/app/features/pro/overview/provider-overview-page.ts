import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { RouterLink } from '@angular/router';

import { ProviderService } from '../../../core/api/provider.service';
import { AuthService } from '../../../core/auth/auth.service';
import { AccountIconComponent } from '../../account/account-icon';
import { Provider, ProviderStatusValue } from '../../../models/provider.model';
import { PROVIDER_NAV } from '../provider-space';

/** Une tuile d'indicateur du tableau de bord (valeur formatée + libellé). */
interface ProviderStat {
  /** Intitulé de l'indicateur. */
  label: string;
  /** Valeur déjà formatée pour l'affichage. */
  value: string;
  /** Tonalité visuelle : `warn` signale un point d'attention (avertissements). */
  tone: 'neutral' | 'warn' | 'good';
}

/** Tonalité de la pastille `.bk-status` selon le statut de validation. */
const STATUS_TONE: Record<ProviderStatusValue, 'pending' | 'active' | 'cancelled' | 'done'> = {
  en_attente: 'pending',
  valide: 'active',
  refuse: 'cancelled',
  suspendu: 'done',
};

/**
 * Accueil de l'**espace prestataire** (F5.1) — tableau de bord.
 *
 * Interroge `GET /providers/mine` (service `ProviderService`) pour afficher
 * l'état réel du profil marketplace du prestataire connecté : **statut de
 * validation** (le point clé — il conditionne la publication de ses services),
 * note moyenne, certifications et avertissements de modération.
 *
 * Trois cas sont distingués : chargement en cours, échec réseau, et surtout le
 * **404 « pas encore de profil »** (`mine` renvoie 404 tant qu'aucun profil
 * n'existe) → on invite alors à finaliser l'inscription prestataire.
 *
 * Les **tuiles de sections** (Mes services, Missions, Revenus…) reprennent
 * `PROVIDER_NAV` : celles non encore construites sont marquées « Bientôt ».
 */
@Component({
  selector: 'app-provider-overview-page',
  imports: [RouterLink, AccountIconComponent],
  templateUrl: './provider-overview-page.html',
  styleUrl: './provider-overview-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProviderOverviewPageComponent {
  private readonly auth = inject(AuthService);
  private readonly providers = inject(ProviderService);

  /** Utilisateur connecté (prestataire). */
  protected readonly user = this.auth.user;

  /** Prénom (premier mot du nom) pour une salutation courte. */
  protected readonly firstName = computed(() => (this.user()?.name ?? '').split(' ')[0] || '');

  /** Chargement du profil en cours. */
  protected readonly loading = signal(true);
  /** Le chargement a échoué (réseau/serveur, hors 404). */
  protected readonly loadError = signal(false);
  /** Aucun profil prestataire (404) : l'utilisateur a le rôle mais pas de dossier. */
  protected readonly noProfile = signal(false);
  /** Profil prestataire renvoyé par l'API (null tant que non chargé). */
  protected readonly provider = signal<Provider | null>(null);

  /** Tuiles de sections (toutes les rubriques sauf l'accueil). */
  protected readonly sections = PROVIDER_NAV.filter((item) => item.path !== '');

  /** Tonalité de la pastille de statut de validation. */
  protected readonly statusTone = computed(() => {
    const status = this.provider()?.status as ProviderStatusValue | undefined;
    return status ? STATUS_TONE[status] : 'done';
  });

  /** Le prestataire est-il validé (donc autorisé à publier) ? */
  protected readonly isValidated = computed(() => this.provider()?.status === 'valide');

  /** Certifications du profil (tableau sûr, même absent). */
  protected readonly certifications = computed(() => this.provider()?.certifications ?? []);

  /** Indicateurs dérivés du profil, prêts à afficher. */
  protected readonly stats = computed<ProviderStat[]>(() => {
    const p = this.provider();
    if (!p) {
      return [];
    }
    const certs = p.certifications ?? [];
    const verified = certs.filter((c) => c.verified).length;
    return [
      { label: 'Note moyenne', value: this.rating(p), tone: 'good' },
      { label: 'Avis reçus', value: this.count(p.rating_count ?? 0), tone: 'neutral' },
      {
        label: 'Certifications',
        value: `${certs.length} · ${verified} vérifiée${verified > 1 ? 's' : ''}`,
        tone: 'neutral',
      },
      {
        label: 'Avertissements',
        value: this.count(p.warnings_count ?? 0),
        tone: (p.warnings_count ?? 0) > 0 ? 'warn' : 'neutral',
      },
    ];
  });

  constructor() {
    this.load();
  }

  /**
   * Charge le profil prestataire. Guardé par la présence d'une session : côté
   * serveur (SSR) le jeton en mémoire est absent ; la route est de toute façon
   * protégée par le rôle prestataire.
   */
  protected load(): void {
    if (!this.user()) {
      this.loading.set(false);
      return;
    }
    this.loading.set(true);
    this.loadError.set(false);
    this.noProfile.set(false);
    this.providers.mine().subscribe({
      next: (res) => {
        this.provider.set(res.data.provider);
        this.loading.set(false);
      },
      error: (err: HttpErrorResponse) => {
        // 404 = compte prestataire sans profil marketplace (cas normal à gérer),
        // distinct d'un vrai échec réseau/serveur.
        if (err.status === 404) {
          this.noProfile.set(true);
        } else {
          this.loadError.set(true);
        }
        this.loading.set(false);
      },
    });
  }

  /** Formate la note moyenne (« 4,6 / 5 » ; « — » si aucune note). */
  private rating(p: Provider): string {
    if (!p.rating_count || Number(p.rating_avg) <= 0) {
      return '—';
    }
    const value = Number(p.rating_avg).toLocaleString('fr-FR', {
      minimumFractionDigits: 1,
      maximumFractionDigits: 1,
    });
    return `${value} / 5`;
  }

  /** Formate un compteur simple (entier). */
  private count(value: number): string {
    return value.toLocaleString('fr-FR');
  }
}
