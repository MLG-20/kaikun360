import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { ActivatedRoute } from '@angular/router';

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

  /**
   * Arrive-t-on ici après un refus de rubrique ? (F7.4.a)
   *
   * Le `permissionGuard` renvoie sur cette page avec `?acces=refuse`. Sans ce
   * message, quelqu'un qui ouvre une URL mise en favori — ou un lien reçu d'un
   * collègue mieux doté — se retrouverait sur la Vue d'ensemble sans comprendre
   * pourquoi, et croirait à un bug plutôt qu'à un droit manquant.
   */
  protected readonly accesRefuse = signal(false);

  constructor() {
    if (inject(ActivatedRoute).snapshot.queryParamMap.get('acces') === 'refuse') {
      this.accesRefuse.set(true);
    }

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
