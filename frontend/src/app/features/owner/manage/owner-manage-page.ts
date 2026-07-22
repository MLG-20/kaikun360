import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { ManageService } from '../../../core/api/manage.service';
import { PageMeta } from '../../../core/api/pagination.model';
import { Mandate } from '../../../models/manage.model';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';
import { mandateLocality, mandatePropertyTitle, mandateTone } from './mandate-status';

@Component({
  selector: 'app-owner-manage-page',
  imports: [RouterLink, BackLinkComponent],
  templateUrl: './owner-manage-page.html',
  styleUrl: './owner-manage-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
/**
 * Écran « Gestion locative » de l'espace propriétaire (F4.4), monté sous
 * `/espace-proprietaire/gestion-locative`. Liste en lecture seule des **mandats
 * de gestion** du propriétaire connecté (`GET /manage/mandates/mine`, paginé
 * 15/page, plus récents d'abord).
 *
 * Chaque mandat est une carte cliquable menant à sa fiche (`gestion-locative/:id`)
 * où l'on retrouve le détail des loyers, reversements, incidents et le rapport
 * mensuel. La carte résume l'essentiel : bien géré, statut du mandat, loyers
 * encaissés / impayés et incidents ouverts. Les mandats sont créés par les
 * agents Kaikun (le propriétaire ne fait que consulter).
 */
export class OwnerManagePageComponent {
  private readonly manage = inject(ManageService);

  // — État de l'écran —
  protected readonly loading = signal(true);
  protected readonly loadError = signal(false);
  protected readonly items = signal<Mandate[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);

  /** Y a-t-il d'autres pages avant / après la page courante ? */
  protected readonly hasPrev = computed(() => (this.meta()?.current_page ?? 1) > 1);
  protected readonly hasNext = computed(() => {
    const m = this.meta();
    return !!m && m.current_page < m.last_page;
  });

  // Helpers de présentation (partagés avec la fiche).
  protected readonly toneOf = mandateTone;
  protected readonly titleOf = mandatePropertyTitle;
  protected readonly localityOf = mandateLocality;
  protected readonly fcfa = (n: number | null | undefined): string | null => formatFcfa(n ?? null);

  constructor() {
    this.load(1);
  }

  /** Charge une page de mandats (remplace la liste affichée). */
  protected load(page: number): void {
    this.loading.set(true);
    this.loadError.set(false);
    this.manage.myMandates(page).subscribe({
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

  /** Page précédente / suivante (bornées par la pagination). */
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
}
