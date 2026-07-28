import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

import { AdminService, DiasporaProject, DiasporaQuery } from '../../../core/api/admin.service';

/** Option générique d'un menu déroulant (valeur + libellé). */
interface SelectOption {
  value: string;
  label: string;
}

/**
 * Écran **Diaspora** du back-office (F7.2.i) — CDC §6 *Diaspora*.
 *
 * File **priorisée** des dossiers pilotés à distance (`GET /diaspora-projects`,
 * dossiers à forte valeur — stratégique > haute > normale — en tête), filtrable
 * par statut, priorité et recherche (référence / pays / client). Un clic sur une
 * ligne ouvre la **fiche** (`/back-office/diaspora/:id`) où l'on priorise, affecte
 * un agent dédié, fait progresser le statut et suit les **rapports** (vérification,
 * chantier, reporting).
 */
@Component({
  selector: 'app-backoffice-diaspora-page',
  imports: [FormsModule],
  templateUrl: './backoffice-diaspora-page.html',
  styleUrl: './backoffice-diaspora-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeDiasporaPageComponent {
  private readonly admin = inject(AdminService);
  private readonly router = inject(Router);

  protected readonly rows = signal<DiasporaProject[]>([]);
  protected readonly total = signal(0);
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  /** Filtres. */
  protected status = '';
  protected priority = '';
  protected search = '';

  protected readonly statusOptions: readonly SelectOption[] = [
    { value: '', label: 'Tous les statuts' },
    { value: 'nouveau', label: 'Nouveau' },
    { value: 'en_cours', label: 'En cours' },
    { value: 'termine', label: 'Terminé' },
    { value: 'annule', label: 'Annulé' },
  ];

  protected readonly priorityOptions: readonly SelectOption[] = [
    { value: '', label: 'Toutes priorités' },
    { value: 'strategique', label: 'Stratégique' },
    { value: 'haute', label: 'Haute' },
    { value: 'normale', label: 'Normale' },
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

    const query: DiasporaQuery = {
      status: this.status || undefined,
      priority: this.priority || undefined,
      q: this.search.trim() || undefined,
      page: this.page(),
    };

    this.admin.diasporaProjects(query).subscribe({
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

  /** Ouvre la fiche d'un dossier. */
  protected open(project: DiasporaProject): void {
    void this.router.navigate(['/back-office', 'diaspora', project.id]);
  }

  // --- Présentation -----------------------------------------------------------

  /** Classe CSS du badge de priorité. */
  protected priorityClass(priority: string | null): string {
    switch (priority) {
      case 'strategique':
        return 'is-strat';
      case 'haute':
        return 'is-high';
      default:
        return 'is-normal';
    }
  }

  /** Classe CSS du badge de statut. */
  protected statusClass(status: string | null): string {
    switch (status) {
      case 'termine':
        return 'is-ok';
      case 'en_cours':
        return 'is-info';
      case 'nouveau':
        return 'is-new';
      case 'annule':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  /** Montant formaté en FCFA. */
  protected xof(value: number | null): string {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('fr-FR').format(value) + ' F';
  }
}
