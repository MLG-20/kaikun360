import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { forkJoin } from 'rxjs';

import { DiasporaService } from '../../../core/api/diaspora.service';
import { DiasporaProject, DiasporaReport } from '../../../models/diaspora.model';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';

/** État de chargement de l'écran. */
type LoadState = 'loading' | 'ready' | 'not-found' | 'error';

/**
 * Détail d'un **projet diaspora** (F3.8), monté sous `/mon-espace/diaspora/:id`.
 *
 * Charge en parallèle le projet (`GET /diaspora-projects/{id}`) et ses
 * **rapports de suivi** (`GET /diaspora-projects/{id}/reports`), affichés en
 * **chronologie** (type, date, commentaire, photos, vidéo). C'est le geste
 * central du critère CDC §15 (« suivi et enrichi de rapports »). Le client est
 * en lecture seule : les rapports sont déposés par l'agent affecté (back-office).
 *
 * Le backend renvoie **404** si le projet n'existe pas ou n'appartient pas au
 * client (isolation par policy) → état « not-found ».
 */
@Component({
  selector: 'app-diaspora-project-detail-page',
  imports: [BackLinkComponent],
  templateUrl: './diaspora-project-detail-page.html',
  styleUrl: './diaspora-project-detail-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DiasporaProjectDetailPageComponent {
  private readonly diaspora = inject(DiasporaService);
  private readonly route = inject(ActivatedRoute);

  protected readonly state = signal<LoadState>('loading');
  protected readonly project = signal<DiasporaProject | null>(null);
  protected readonly reports = signal<DiasporaReport[]>([]);

  /** Formatage FCFA lisible (mutualisé avec le catalogue public). */
  protected readonly fcfa = formatFcfa;

  constructor() {
    const id = Number(this.route.snapshot.paramMap.get('id'));
    forkJoin({
      project: this.diaspora.project(id),
      reports: this.diaspora.reports(id),
    }).subscribe({
      next: ({ project, reports }) => {
        this.project.set(project.data.project);
        this.reports.set(reports.data);
        this.state.set('ready');
      },
      error: (err: { status?: number }) => {
        this.state.set(err?.status === 404 || err?.status === 403 ? 'not-found' : 'error');
      },
    });
  }

  /** Classe CSS du badge de statut (mêmes valeurs que l'enum backend). */
  protected statusClass(status: string | null): string {
    switch (status) {
      case 'termine':
        return 'is-done';
      case 'en_cours':
        return 'is-active';
      case 'annule':
        return 'is-cancelled';
      default:
        return 'is-new';
    }
  }
}
