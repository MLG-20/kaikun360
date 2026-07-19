import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { PropertyManagementService } from '../../../core/api/property-management.service';
import { PageMeta } from '../../../core/api/pagination.model';
import { Property } from '../../../models/property.model';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';
import { propertyLocality, propertyStatus } from './property-status';

@Component({
  selector: 'app-owner-properties-page',
  imports: [DatePipe, RouterLink, BackLinkComponent],
  templateUrl: './owner-properties-page.html',
  styleUrl: './owner-properties-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
/**
 * Écran « Mes biens » de l'espace propriétaire (F4.2), monté sous
 * `/espace-proprietaire/biens`. Liste en lecture seule des biens du propriétaire
 * connecté, **tous statuts confondus** (`GET /properties/mine`, paginé 15/page,
 * plus récents d'abord).
 *
 * Chaque bien est une carte cliquable menant à sa fiche (`biens/:id`) : titre,
 * type, localité, prix et surtout **pastille de statut de validation** (publié /
 * en attente / rejeté…) — le propriétaire suit ainsi la mise en ligne de ses
 * annonces. Le dépôt et l'édition d'un bien se feront en F4.3 ; cet écran ne
 * fait que lister et consulter.
 */
export class OwnerPropertiesPageComponent {
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

  // Helpers de présentation (partagés avec la fiche).
  protected readonly statusOf = propertyStatus;
  protected readonly localityOf = propertyLocality;
  protected readonly priceOf = (p: Property): string | null => formatFcfa(p.price_xof);

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
        // Ramène en haut de liste au changement de page (confort de lecture).
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
