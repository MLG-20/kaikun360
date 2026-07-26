import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { DiasporaService } from '../../../core/api/diaspora.service';
import { DiasporaProject } from '../../../models/diaspora.model';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';

/** État de chargement de l'écran. */
type LoadState = 'loading' | 'ready' | 'error';

/**
 * Écran « Mes projets diaspora » de l'espace client (F3.8), monté sous
 * `/mon-espace/diaspora`.
 *
 * Liste les dossiers pilotés à distance du client (`GET /diaspora-projects/mine`)
 * avec leur **statut**, leur priorité et le **nombre de rapports** reçus, et
 * donne accès à la création (`diaspora/nouveau`) et au détail (`diaspora/:id`).
 * Comble l'exigence CDC §15 (dossier diaspora « créé, suivi et enrichi de
 * rapports »), restée sans interface côté client.
 */
@Component({
  selector: 'app-diaspora-projects-page',
  imports: [RouterLink, BackLinkComponent],
  templateUrl: './diaspora-projects-page.html',
  styleUrl: './diaspora-projects-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DiasporaProjectsPageComponent {
  private readonly diaspora = inject(DiasporaService);

  protected readonly state = signal<LoadState>('loading');
  protected readonly projects = signal<DiasporaProject[]>([]);

  /** Formatage FCFA lisible (mutualisé avec le catalogue public). */
  protected readonly fcfa = formatFcfa;

  constructor() {
    this.load();
  }

  /** Charge mes projets diaspora (première page). */
  protected load(): void {
    this.state.set('loading');
    this.diaspora.myProjects().subscribe({
      next: (res) => {
        this.projects.set(res.data);
        this.state.set('ready');
      },
      error: () => this.state.set('error'),
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
