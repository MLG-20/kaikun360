import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

import {
  AdminService,
  RequestQueueEntry,
  RequestQueueFilters,
  RequestQueueQuery,
  RequestTransition,
} from '../../../core/api/admin.service';

/**
 * Écran **Demandes** du back-office — la file de traitement (F8.9).
 *
 * ⚠️ **Cet écran comble un trou, il n'ajoute pas un confort.** Depuis B11.2,
 * toute demande déposée depuis le site public (« Demander une réservation »,
 * formulaires de contact métier) déclenchait une alerte interne — « Nouvelle
 * demande à traiter », avec un bouton *Ouvrir la file de traitement* — mais
 * cette file n'existait nulle part. L'équipe recevait donc l'e-mail d'un
 * dossier qu'elle n'avait aucun moyen de retrouver : la Vue d'ensemble en
 * affichait le nombre, rien de plus.
 *
 * Trois partis pris :
 *  - **les urgences d'abord, puis les plus anciennes** (tri décidé côté
 *    serveur) : dans une file, le dossier qui attend depuis le plus longtemps
 *    coûte plus cher que le dernier arrivé ;
 *  - **le demandeur est joignable dès la liste** — traiter une demande commence
 *    presque toujours par rappeler son auteur ;
 *  - **les filtres viennent du backend** (`GET /admin/requests/filters`) :
 *    recopier ici les libellés des enums PHP les ferait diverger au premier
 *    statut ajouté.
 */
@Component({
  selector: 'app-backoffice-requests-page',
  imports: [FormsModule],
  templateUrl: './backoffice-requests-page.html',
  styleUrl: './backoffice-requests-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeRequestsPageComponent {
  private readonly admin = inject(AdminService);
  private readonly router = inject(Router);

  protected readonly rows = signal<RequestQueueEntry[]>([]);
  protected readonly total = signal(0);
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  protected status = '';
  protected serviceType = '';
  protected priority = '';
  protected search = '';

  protected readonly statusOptions = signal<RequestTransition[]>([]);
  protected readonly serviceOptions = signal<RequestTransition[]>([]);
  protected readonly priorityOptions = signal<RequestTransition[]>([]);

  constructor() {
    this.loadFilters();
    this.load();
  }

  /**
   * Référentiels de filtrage. Un échec n'est pas bloquant : la file reste
   * consultable sans filtres plutôt que de ne rien afficher du tout.
   */
  private loadFilters(): void {
    this.admin.requestQueueFilters().subscribe({
      next: (filters: RequestQueueFilters) => {
        this.statusOptions.set(filters.statuses);
        this.serviceOptions.set(filters.service_types);
        this.priorityOptions.set(filters.priorities);
      },
      error: () => {
        /* file utilisable sans filtres */
      },
    });
  }

  protected applyFilters(): void {
    this.page.set(1);
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.error.set(false);

    const query: RequestQueueQuery = {
      status: this.status || undefined,
      service_type: this.serviceType || undefined,
      priority: this.priority || undefined,
      search: this.search.trim() || undefined,
      page: this.page(),
    };

    this.admin.requestQueue(query).subscribe({
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

  /** Ouvre la fiche de la demande. */
  protected open(entry: RequestQueueEntry): void {
    void this.router.navigate(['/back-office', 'demandes', entry.id]);
  }

  // --- Présentation -----------------------------------------------------------

  /**
   * Une demande clôturée est éteinte ; tout le reste est « en cours », car une
   * demande ouverte est un engagement de délai, pas un état neutre.
   */
  protected statusClass(status: string | null): string {
    switch (status) {
      case 'cloture':
        return 'is-off';
      case 'recu':
        return 'is-pending';
      default:
        return 'is-active';
    }
  }

  protected priorityClass(priority: string | null): string {
    switch (priority) {
      case 'urgente':
        return 'is-urgent';
      case 'haute':
        return 'is-high';
      default:
        return '';
    }
  }

  protected xof(value: number | null | undefined): string {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('fr-FR').format(value) + ' F';
  }

  /**
   * Ancienneté en clair. Une file se juge à l'attente : « il y a 6 jours » dit
   * bien plus qu'une date, qu'il faudrait comparer mentalement à aujourd'hui.
   */
  protected age(iso: string | null): string {
    if (!iso) return '—';
    const jours = Math.floor((Date.now() - new Date(iso).getTime()) / 86_400_000);
    if (jours <= 0) return "aujourd'hui";
    if (jours === 1) return 'hier';
    return `il y a ${jours} jours`;
  }

  /** Première ligne du message : la table ne peut pas porter le récit entier. */
  protected extrait(message: string | null): string {
    if (!message) return '—';
    const propre = message.replace(/\s+/g, ' ').trim();
    return propre.length > 90 ? `${propre.slice(0, 90)}…` : propre;
  }
}
