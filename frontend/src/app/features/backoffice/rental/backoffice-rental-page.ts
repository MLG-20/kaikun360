import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

import { AdminService, MandateDossier, MandateQuery } from '../../../core/api/admin.service';

/** Option de filtre par statut. */
interface StatusOption {
  value: string;
  label: string;
}

/**
 * Écran **Gestion locative** du back-office — CDC §6 *Gestion locative*.
 *
 * Anciennement un onglet de l'écran « Dossiers », partagé avec la construction.
 * Les deux métiers n'ont ni le même cycle ni les mêmes gestes : chacun a
 * désormais sa rubrique au rail (F7.3.c).
 *
 * Liste des mandats de gestion de tous les propriétaires
 * (`GET /admin/mandates`, garde `consulter:dashboard-admin`) avec le bien géré,
 * le propriétaire, la commission, la période et les agrégats financiers —
 * **loyers impayés en alerte**, dépenses, reversements, incidents ouverts.
 * Chaque ligne ouvre la **fiche pilotable** du mandat (F7.3.a), où se font
 * réellement les encaissements, les incidents, les dépenses et les reversements.
 */
@Component({
  selector: 'app-backoffice-rental-page',
  imports: [FormsModule],
  templateUrl: './backoffice-rental-page.html',
  styleUrl: './backoffice-rental-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeRentalPageComponent {
  private readonly admin = inject(AdminService);
  private readonly router = inject(Router);

  protected readonly rows = signal<MandateDossier[]>([]);
  protected readonly total = signal(0);
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  protected status = '';

  protected readonly statusOptions: readonly StatusOption[] = [
    { value: '', label: 'Tous les statuts' },
    { value: 'en_attente', label: 'En attente' },
    { value: 'actif', label: 'Actif' },
    { value: 'suspendu', label: 'Suspendu' },
    { value: 'termine', label: 'Terminé' },
  ];

  constructor() {
    this.load();
  }

  protected applyFilters(): void {
    this.page.set(1);
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.error.set(false);

    const query: MandateQuery = {
      status: this.status || undefined,
      page: this.page(),
    };

    this.admin.adminMandates(query).subscribe({
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

  /** Ouvre la fiche PILOTABLE du mandat (F7.3.a). */
  protected open(mandate: MandateDossier): void {
    void this.router.navigate(['/back-office', 'gestion-locative', mandate.id]);
  }

  // --- Présentation -----------------------------------------------------------

  protected statusClass(status: string | null): string {
    switch (status) {
      case 'actif':
        return 'is-ok';
      case 'suspendu':
        return 'is-warn';
      case 'termine':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  /** Localisation courte du bien géré (commune · département · région). */
  protected placeOf(mandate: MandateDossier): string {
    const location = mandate.property.location;
    if (!location) return '—';
    const parts = [location.commune, location.department, location.region].filter(
      (part): part is string => !!part,
    );
    return parts.length ? parts.join(' · ') : '—';
  }

  /** Taux de commission formaté en pourcentage. */
  protected rate(value: number | string | null): string {
    if (value === null || value === undefined) return '—';
    const n = typeof value === 'string' ? Number(value) : value;
    if (Number.isNaN(n)) return '—';
    return `${n} %`;
  }

  /** Montant formaté en FCFA. */
  protected xof(value: number | null | undefined): string {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('fr-FR').format(value) + ' F';
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
}
