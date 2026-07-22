import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { PropertyManagementService } from '../../../core/api/property-management.service';
import { PageMeta } from '../../../core/api/pagination.model';
import { Property } from '../../../models/property.model';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';
import { propertyLocality, propertyStatus } from '../properties/property-status';

@Component({
  selector: 'app-owner-documents-page',
  imports: [RouterLink, BackLinkComponent],
  templateUrl: './owner-documents-page.html',
  styleUrl: './owner-documents-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
/**
 * Écran « Documents » de l'espace propriétaire (F4.5), monté sous
 * `/espace-proprietaire/documents`. Point d'entrée de la gestion documentaire :
 * il liste les biens du propriétaire (`GET /properties/mine`, paginé 15/page)
 * en affichant, pour chacun, le **nombre de pièces justificatives** déjà
 * déposées (`documents_count`).
 *
 * Chaque bien est une carte cliquable menant à la gestion de ses documents
 * (`documents/:id`) : titre foncier, bail, plan… y sont déposés, téléchargés
 * (lien signé) et retirés. On regroupe ainsi les documents PAR bien, là où le
 * propriétaire les cherche naturellement.
 */
export class OwnerDocumentsPageComponent {
  private readonly properties = inject(PropertyManagementService);

  // — État de l'écran —
  protected readonly loading = signal(true);
  protected readonly loadError = signal(false);
  protected readonly items = signal<Property[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);

  /** Y a-t-il d'autres pages avant / après la page courante ? */
  protected readonly hasPrev = computed(() => (this.meta()?.current_page ?? 1) > 1);
  protected readonly hasNext = computed(() => {
    const m = this.meta();
    return !!m && m.current_page < m.last_page;
  });

  // Helpers de présentation (partagés avec « Mes biens »).
  protected readonly statusOf = propertyStatus;
  protected readonly localityOf = propertyLocality;

  constructor() {
    this.load(1);
  }

  /** Charge une page de biens (remplace la liste affichée). */
  protected load(page: number): void {
    this.loading.set(true);
    this.loadError.set(false);
    this.properties.mine(page).subscribe({
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

  /** Libellé du compteur de documents (accord singulier/pluriel). */
  protected docLabel(count: number | undefined): string {
    const n = count ?? 0;
    if (n === 0) {
      return 'Aucun document';
    }
    return n === 1 ? '1 document' : `${n} documents`;
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
