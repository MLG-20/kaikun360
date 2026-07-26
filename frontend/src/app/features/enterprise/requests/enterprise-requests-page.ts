import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { PageMeta } from '../../../core/api/pagination.model';
import { TeamBuildingService } from '../../../core/api/team-building.service';
import { TeamBuildingRequest } from '../../../models/team-building.model';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';
import { ENTERPRISE_SPACE } from '../enterprise-space';
import { requestStatus } from './team-building-status';

/**
 * Écran **Mes demandes** de l'espace entreprise (F6) — route
 * `/espace-entreprise/demandes`. Historique et suivi des demandes de team
 * building de l'entreprise (`GET /team-building-requests/mine`, paginé 15,
 * récentes d'abord).
 *
 * Chaque demande est une carte cliquable : référence, statut (pastille), ville,
 * participants, dates et budget. Un bouton permet de lancer une nouvelle
 * demande. Le détail (avec les devis) est atteint en cliquant une carte.
 */
@Component({
  selector: 'app-enterprise-requests-page',
  imports: [DatePipe, RouterLink],
  templateUrl: './enterprise-requests-page.html',
  styleUrl: './enterprise-requests-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class EnterpriseRequestsPageComponent {
  private readonly teamBuilding = inject(TeamBuildingService);

  /** Préfixe d'URL de l'espace (liens). */
  protected readonly base = ENTERPRISE_SPACE.basePath;

  // — État de l'écran —
  protected readonly loading = signal(true);
  protected readonly loadError = signal(false);
  protected readonly items = signal<TeamBuildingRequest[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);

  protected readonly hasPrev = computed(() => (this.meta()?.current_page ?? 1) > 1);
  protected readonly hasNext = computed(() => {
    const m = this.meta();
    return !!m && m.current_page < m.last_page;
  });

  constructor() {
    this.load(1);
  }

  /** Charge une page de demandes. */
  protected load(page: number): void {
    this.loading.set(true);
    this.loadError.set(false);
    this.teamBuilding.myRequests(page).subscribe({
      next: (res) => {
        this.items.set(res.data);
        this.meta.set(res.meta);
        this.loading.set(false);
        if (typeof window !== 'undefined') {
          window.scrollTo({ top: 0, behavior: 'smooth' });
        }
      },
      error: () => {
        this.loading.set(false);
        this.loadError.set(true);
      },
    });
  }

  protected prev(): void {
    if (this.hasPrev()) {
      this.load((this.meta()?.current_page ?? 2) - 1);
    }
  }

  protected next(): void {
    if (this.hasNext()) {
      this.load((this.meta()?.current_page ?? 0) + 1);
    }
  }

  /** Présentation (libellé + tonalité) du statut d'une demande. */
  protected status(req: TeamBuildingRequest) {
    return requestStatus(req.status);
  }

  /** Budget formaté en FCFA (ou null si non renseigné). */
  protected budget(req: TeamBuildingRequest): string | null {
    return formatFcfa(req.budget_xof);
  }
}
