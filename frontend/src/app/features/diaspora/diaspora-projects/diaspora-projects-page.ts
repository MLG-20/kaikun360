import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { DiasporaService } from '../../../core/api/diaspora.service';
import { DiasporaProject } from '../../../models/diaspora.model';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';

/** État de chargement de l'écran. */
type LoadState = 'loading' | 'ready' | 'error';

/**
 * Écran « Mes projets diaspora » (F3.8), page d'ACCUEIL du désormais
 * indépendant espace diaspora (F18, `/espace-diaspora`).
 *
 * Liste les dossiers pilotés à distance du client (`GET /diaspora-projects/mine`)
 * avec leur **statut**, leur priorité et le **nombre de rapports** reçus, et
 * donne accès à la création (`nouveau`) et au détail (`:id`). Comble l'exigence
 * CDC §15 (dossier diaspora « créé, suivi et enrichi de rapports »).
 *
 * ⚠️ **Le bloc « Mes chantiers & devis » (F3.9) a QUITTÉ cet écran** lors de la
 * séparation de l'espace diaspora (2026-08-22) : il s'adresse à TOUS les
 * clients (rattachement par client, pas par projet diaspora), il vit
 * désormais sur l'accueil de l'espace CLIENT (`account-overview-page`), pas
 * ici — sans quoi un client résident aurait perdu son seul moyen de répondre
 * à un devis de chantier.
 */
@Component({
  selector: 'app-diaspora-projects-page',
  imports: [RouterLink],
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
