import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

import {
  AdminService,
  TeamBuildingQuery,
  TeamBuildingRequestItem,
} from '../../../core/api/admin.service';

/** Option générique d'un menu déroulant (valeur + libellé). */
interface SelectOption {
  value: string;
  label: string;
}

/**
 * Écran **Team building** du back-office (F7.2.h) — CDC §6 *Team building*.
 *
 * File des demandes groupées déposées par les entreprises
 * (`GET /team-building-requests`, triée nouveau → annulé), filtrable par statut
 * et recherche (référence / ville / entreprise). Un clic sur une ligne ouvre la
 * **fiche** (`/back-office/team-building/:id`) où l'on compose/envoie le devis du
 * pack et où l'on **affecte les prestataires** (exigence CDC « affectation
 * prestataires »).
 */
@Component({
  selector: 'app-backoffice-team-building-page',
  imports: [FormsModule],
  templateUrl: './backoffice-team-building-page.html',
  styleUrl: './backoffice-team-building-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeTeamBuildingPageComponent {
  private readonly admin = inject(AdminService);
  private readonly router = inject(Router);

  protected readonly rows = signal<TeamBuildingRequestItem[]>([]);
  protected readonly total = signal(0);
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  /** Filtres. */
  protected status = '';
  protected search = '';

  protected readonly statusOptions: readonly SelectOption[] = [
    { value: '', label: 'Tous les statuts' },
    { value: 'nouveau', label: 'Nouveau' },
    { value: 'en_etude', label: 'En étude' },
    { value: 'devis_envoye', label: 'Devis envoyé' },
    { value: 'accepte', label: 'Accepté' },
    { value: 'annule', label: 'Annulé' },
  ];

  constructor() {
    this.load();
  }

  /** Applique les filtres depuis la première page. */
  protected applyFilters(): void {
    this.page.set(1);
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.error.set(false);

    const query: TeamBuildingQuery = {
      status: this.status || undefined,
      q: this.search.trim() || undefined,
      page: this.page(),
    };

    this.admin.teamBuildingRequests(query).subscribe({
      next: (paginated) => {
        this.rows.set(paginated.data);
        this.total.set(paginated.meta.total);
        this.lastPage.set(paginated.meta.last_page);
        this.loading.set(false);
      },
      error: () => {
        this.error.set(true);
        this.loading.set(false);
      },
    });
  }

  protected goTo(page: number): void {
    if (page < 1 || page > this.lastPage() || page === this.page()) return;
    this.page.set(page);
    this.load();
  }

  /** Ouvre la fiche d'une demande. */
  protected open(request: TeamBuildingRequestItem): void {
    void this.router.navigate(['/back-office', 'team-building', request.id]);
  }

  // --- Présentation -----------------------------------------------------------

  /** Classe CSS du badge de statut. */
  protected statusClass(status: string | null): string {
    switch (status) {
      case 'accepte':
        return 'is-ok';
      case 'devis_envoye':
        return 'is-info';
      case 'en_etude':
        return 'is-pending';
      case 'nouveau':
        return 'is-new';
      case 'annule':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  /** Période « du … au … » (ou l'une des deux bornes). */
  protected period(request: TeamBuildingRequestItem): string {
    const from = this.shortDate(request.start_date);
    const to = this.shortDate(request.end_date);
    if (from === '—' && to === '—') return '—';
    return `${from} → ${to}`;
  }

  /** Montant formaté en FCFA. */
  protected xof(value: number | null): string {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('fr-FR').format(value) + ' F';
  }

  /** Date courte (jour mois année). */
  protected shortDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }
}
