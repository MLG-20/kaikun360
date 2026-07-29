import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

import {
  AdminService,
  ConstructionDossier,
  ConstructionQuery,
} from '../../../core/api/admin.service';

/** Option de filtre par statut. */
interface StatusOption {
  value: string;
  label: string;
}

/**
 * Écran **Construction** du back-office — CDC §6 *Construction*.
 *
 * Anciennement un onglet de l'écran « Dossiers », qui mélangeait construction et
 * gestion locative. Ces deux métiers n'ont ni le même cycle, ni les mêmes
 * gestes, ni les mêmes personnes derrière : chacun a désormais sa rubrique au
 * rail (F7.3.c).
 *
 * Liste de toutes les demandes de construction / rénovation
 * (`GET /admin/construction-requests`, garde `consulter:dashboard-admin`), avec
 * le **demandeur**, l'objectif, le budget annoncé, le coût estimé, l'avancement
 * (jalons / comptes rendus) et le statut. Filtres statut + ville, pagination.
 * Chaque ligne ouvre la **fiche du dossier** (F7.3.b).
 */
@Component({
  selector: 'app-backoffice-construction-page',
  imports: [FormsModule],
  templateUrl: './backoffice-construction-page.html',
  styleUrl: './backoffice-construction-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeConstructionPageComponent {
  private readonly admin = inject(AdminService);
  private readonly router = inject(Router);

  protected readonly rows = signal<ConstructionDossier[]>([]);
  protected readonly total = signal(0);
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  protected status = '';
  protected city = '';

  protected readonly statusOptions: readonly StatusOption[] = [
    { value: '', label: 'Tous les statuts' },
    { value: 'soumise', label: 'Soumise' },
    { value: 'en_etude', label: 'En étude' },
    { value: 'devis_envoye', label: 'Devis envoyé' },
    { value: 'acceptee', label: 'Acceptée' },
    { value: 'en_chantier', label: 'En chantier' },
    { value: 'terminee', label: 'Terminée' },
    { value: 'annulee', label: 'Annulée' },
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

    const query: ConstructionQuery = {
      status: this.status || undefined,
      city: this.city.trim() || undefined,
      page: this.page(),
    };

    this.admin.adminConstructionRequests(query).subscribe({
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

  /** Ouvre la fiche détaillée d'un dossier (F7.3.b). */
  protected open(dossier: ConstructionDossier): void {
    void this.router.navigate(['/back-office', 'construction', dossier.id]);
  }

  // --- Présentation -----------------------------------------------------------

  protected statusClass(status: string | null): string {
    switch (status) {
      case 'terminee':
        return 'is-ok';
      case 'en_chantier':
      case 'acceptee':
        return 'is-active';
      case 'annulee':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  /** Montant formaté en FCFA. */
  protected xof(value: number | null | undefined): string {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('fr-FR').format(value) + ' F';
  }

  /** Surface formatée en m². */
  protected surface(value: number | null): string {
    if (value === null || value === undefined) return '—';
    return `${new Intl.NumberFormat('fr-FR').format(value)} m²`;
  }
}
