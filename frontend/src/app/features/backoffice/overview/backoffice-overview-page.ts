import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';

import { AdminService, DashboardSnapshot } from '../../../core/api/admin.service';

/**
 * Vue d'ensemble du back-office (F7.1.e) — première rubrique du poste de
 * commandement. Affiche la photographie agrégée renvoyée par
 * `GET /admin/dashboard` : files de validation, activité du jour, revenus
 * estimés, alertes et indicateurs cumulés.
 */
@Component({
  selector: 'app-backoffice-overview-page',
  imports: [],
  templateUrl: './backoffice-overview-page.html',
  styleUrl: './backoffice-overview-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeOverviewPageComponent {
  private readonly admin = inject(AdminService);

  /** Instantané du tableau de bord (null tant que le chargement n'a pas abouti). */
  protected readonly snapshot = signal<DashboardSnapshot | null>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  constructor() {
    this.admin.dashboard().subscribe({
      next: (data) => {
        this.snapshot.set(data);
        this.loading.set(false);
      },
      error: () => {
        this.error.set(true);
        this.loading.set(false);
      },
    });
  }

  /** Total des ressources en attente de validation (somme des files). */
  protected pendingTotal(s: DashboardSnapshot): number {
    const q = s.queues;
    return q.properties_pending + q.vehicles_pending + q.experiences_pending + q.providers_pending;
  }

  /** Formate un montant en FCFA (séparateurs de milliers). */
  protected xof(value: number): string {
    return new Intl.NumberFormat('fr-FR').format(value) + ' F';
  }
}
