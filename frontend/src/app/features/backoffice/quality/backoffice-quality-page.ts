import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';

import {
  AdminReview,
  AdminService,
  ProviderQuery,
  ReviewModeration,
  ReviewQuery,
} from '../../../core/api/admin.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import { Provider } from '../../../models/provider.model';

/** Onglet actif de l'écran Avis & qualité. */
type QualityTab = 'reviews' | 'providers';

/** Option générique d'un menu déroulant (valeur + libellé). */
interface SelectOption {
  value: string;
  label: string;
}

/**
 * Écran **Avis & qualité** du back-office (F7.2.g) — CDC §6 *Avis et qualité*
 * (modération avis · notation prestataire · sanctions).
 *
 * Deux onglets :
 *   - **Avis à modérer** (`GET /admin/reviews`, défaut `en_attente`) : file de
 *     modération tous types confondus (biens / véhicules / expériences /
 *     prestataires), avec **publier / rejeter** (`PATCH /reviews/{id}/moderate`).
 *     Une publication répercute la note du prestataire côté serveur.
 *   - **Prestataires** (`GET /admin/providers`) : supervision de la note agrégée
 *     et de la charte qualité — **avertir** (`PATCH /providers/{id}/warn`, au-delà
 *     du seuil = suspension d'office) et **suspendre** (`PATCH …/suspend`), motif
 *     obligatoire, dans un panneau déplié par ligne.
 *
 * Les **incidents** (module Manage) ne sont pas dupliqués ici : ils restent
 * pilotés dans l'écran **Dossiers** (F7.2.e) — un renvoi y est affiché.
 */
@Component({
  selector: 'app-backoffice-quality-page',
  imports: [FormsModule, RouterLink],
  templateUrl: './backoffice-quality-page.html',
  styleUrl: './backoffice-quality-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeQualityPageComponent {
  private readonly admin = inject(AdminService);

  /** Onglet courant. */
  protected readonly tab = signal<QualityTab>('reviews');

  // --- Onglet Avis ------------------------------------------------------------
  protected readonly reviews = signal<AdminReview[]>([]);
  protected readonly reviewsTotal = signal(0);
  protected readonly reviewsPage = signal(1);
  protected readonly reviewsLastPage = signal(1);
  protected readonly reviewsLoading = signal(true);
  protected readonly reviewsError = signal(false);
  protected readonly reviewActionError = signal<string | null>(null);
  protected reviewStatus = 'en_attente';
  protected reviewSearch = '';

  // --- Onglet Prestataires ----------------------------------------------------
  protected readonly providers = signal<Provider[]>([]);
  protected readonly providersTotal = signal(0);
  protected readonly providersPage = signal(1);
  protected readonly providersLastPage = signal(1);
  protected readonly providersLoading = signal(false);
  protected readonly providersError = signal(false);
  protected readonly providersLoaded = signal(false);
  protected providerStatus = '';
  protected providerSearch = '';

  /** Ligne prestataire dépliée (panneau de sanction), ou null. */
  protected readonly expandedProviderId = signal<number | null>(null);
  protected readonly providerActionError = signal<string | null>(null);
  /** Motif saisi dans le panneau de sanction. */
  protected sanctionReason = '';

  protected readonly reviewStatusOptions: readonly SelectOption[] = [
    { value: 'en_attente', label: 'En attente' },
    { value: 'publie', label: 'Publiés' },
    { value: 'rejete', label: 'Rejetés' },
  ];

  protected readonly providerStatusOptions: readonly SelectOption[] = [
    { value: '', label: 'Tous les statuts' },
    { value: 'valide', label: 'Validé' },
    { value: 'en_attente', label: 'En attente' },
    { value: 'suspendu', label: 'Suspendu' },
    { value: 'refuse', label: 'Refusé' },
  ];

  /** Libellés lisibles des types de ressource notée. */
  private readonly resourceTypeLabels: Record<string, string> = {
    stay: 'Nuitée',
    vehicle: 'Véhicule',
    experience: 'Expérience',
    provider: 'Prestataire',
  };

  constructor() {
    this.loadReviews();
  }

  /** Bascule d'onglet (charge les prestataires à leur première ouverture). */
  protected switchTab(tab: QualityTab): void {
    if (this.tab() === tab) return;
    this.tab.set(tab);
    if (tab === 'providers' && !this.providersLoaded()) this.loadProviders();
  }

  // --- Avis -------------------------------------------------------------------

  protected applyReviewFilters(): void {
    this.reviewsPage.set(1);
    this.loadReviews();
  }

  protected loadReviews(): void {
    this.reviewsLoading.set(true);
    this.reviewsError.set(false);

    const query: ReviewQuery = {
      status: this.reviewStatus || undefined,
      q: this.reviewSearch.trim() || undefined,
      page: this.reviewsPage(),
    };

    this.admin.adminReviews(query).subscribe({
      next: (paginated) => {
        this.reviews.set(paginated.data);
        this.reviewsTotal.set(paginated.meta.total);
        this.reviewsLastPage.set(paginated.meta.last_page);
        this.reviewsLoading.set(false);
      },
      error: () => {
        this.reviewsError.set(true);
        this.reviewsLoading.set(false);
      },
    });
  }

  protected goToReviews(page: number): void {
    if (page < 1 || page > this.reviewsLastPage() || page === this.reviewsPage()) return;
    this.reviewsPage.set(page);
    this.loadReviews();
  }

  /** Publie ou rejette un avis (retiré de la file s'il n'y est plus). */
  protected moderate(review: AdminReview, decision: ReviewModeration): void {
    this.reviewActionError.set(null);
    this.admin.moderateReview(review.id, decision).subscribe({
      next: () => {
        // Si la file affiche « en attente », l'avis modéré en sort → on le retire.
        if (this.reviewStatus === 'en_attente') {
          this.reviews.update((list) => list.filter((r) => r.id !== review.id));
          this.reviewsTotal.update((n) => Math.max(0, n - 1));
        } else {
          this.loadReviews();
        }
      },
      error: (error: HttpErrorResponse) => this.reviewActionError.set(this.messageFor(error)),
    });
  }

  // --- Prestataires -----------------------------------------------------------

  protected applyProviderFilters(): void {
    this.providersPage.set(1);
    this.loadProviders();
  }

  protected loadProviders(): void {
    this.providersLoading.set(true);
    this.providersError.set(false);
    this.expandedProviderId.set(null);

    const query: ProviderQuery = {
      status: this.providerStatus || undefined,
      q: this.providerSearch.trim() || undefined,
      page: this.providersPage(),
    };

    this.admin.adminProviders(query).subscribe({
      next: (paginated) => {
        this.providers.set(paginated.data);
        this.providersTotal.set(paginated.meta.total);
        this.providersLastPage.set(paginated.meta.last_page);
        this.providersLoaded.set(true);
        this.providersLoading.set(false);
      },
      error: () => {
        this.providersError.set(true);
        this.providersLoading.set(false);
      },
    });
  }

  protected goToProviders(page: number): void {
    if (page < 1 || page > this.providersLastPage() || page === this.providersPage()) return;
    this.providersPage.set(page);
    this.loadProviders();
  }

  /** Ouvre / ferme le panneau de sanction d'un prestataire (réinitialise le motif). */
  protected toggleSanction(provider: Provider): void {
    this.providerActionError.set(null);
    this.sanctionReason = '';
    this.expandedProviderId.update((current) => (current === provider.id ? null : provider.id));
  }

  /** Émet un avertissement (motif obligatoire). */
  protected warn(provider: Provider): void {
    const reason = this.sanctionReason.trim();
    if (!reason) {
      this.providerActionError.set('Le motif est obligatoire.');
      return;
    }
    this.providerActionError.set(null);
    this.admin.warnProvider(provider.id, reason).subscribe({
      next: (updated) => this.afterSanction(updated),
      error: (error: HttpErrorResponse) => this.providerActionError.set(this.messageFor(error)),
    });
  }

  /** Suspend le prestataire (motif obligatoire). */
  protected suspend(provider: Provider): void {
    const reason = this.sanctionReason.trim();
    if (!reason) {
      this.providerActionError.set('Le motif est obligatoire.');
      return;
    }
    this.providerActionError.set(null);
    this.admin.suspendProvider(provider.id, reason).subscribe({
      next: (updated) => this.afterSanction(updated),
      error: (error: HttpErrorResponse) => this.providerActionError.set(this.messageFor(error)),
    });
  }

  /** Remplace le prestataire sanctionné dans la liste et referme le panneau. */
  private afterSanction(updated: Provider): void {
    this.providers.update((list) => list.map((p) => (p.id === updated.id ? updated : p)));
    this.expandedProviderId.set(null);
    this.sanctionReason = '';
  }

  // --- Présentation -----------------------------------------------------------

  /** Étoiles pleines pour une note (1 à 5). */
  protected stars(rating: number): string {
    const full = Math.max(0, Math.min(5, Math.round(rating)));
    return '★★★★★'.slice(0, full) + '☆☆☆☆☆'.slice(0, 5 - full);
  }

  /** Libellé du type de ressource notée. */
  protected resourceTypeLabel(type: string): string {
    return this.resourceTypeLabels[type] ?? 'Ressource';
  }

  /** Note agrégée formatée (ex. « 4.5 / 5 » ou « — »). */
  protected ratingLabel(provider: Provider): string {
    if (provider.rating_avg === null || provider.rating_avg === undefined) return '—';
    const count = provider.rating_count ?? 0;
    return `${Number(provider.rating_avg).toFixed(1)} / 5 (${count})`;
  }

  /** Classe CSS du badge de statut prestataire. */
  protected providerStatusClass(status: string | null): string {
    switch (status) {
      case 'valide':
        return 'is-ok';
      case 'en_attente':
        return 'is-pending';
      case 'suspendu':
        return 'is-warn';
      case 'refuse':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  /** Classe CSS du badge de statut d'avis. */
  protected reviewStatusClass(status: string | null): string {
    switch (status) {
      case 'publie':
        return 'is-ok';
      case 'en_attente':
        return 'is-pending';
      case 'rejete':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  /** Date courte (jour/mois/année). */
  protected shortDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }

  private messageFor(error: HttpErrorResponse): string {
    if (error.status === 422) {
      const body = error.error as ValidationErrorBody | null;
      return body?.message ?? 'Données invalides.';
    }
    if (error.status === 403) {
      const body = error.error as { message?: string } | null;
      return body?.message ?? 'Action non autorisée pour votre rôle.';
    }
    return 'Opération impossible pour le moment. Réessayez.';
  }
}
