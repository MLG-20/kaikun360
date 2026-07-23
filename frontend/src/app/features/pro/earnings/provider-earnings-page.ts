import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { ProviderService } from '../../../core/api/provider.service';
import { ProviderEarnings } from '../../../models/provider.model';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';

/**
 * Écran « Revenus & commissions » de l'espace prestataire (F5.3), monté sous
 * `/espace-prestataire/revenus`. Synthèse financière issue de
 * `GET /provider-missions/earnings` (agrégat scopé au prestataire connecté).
 *
 * Deux blocs de lecture : le **réalisé** (missions terminées → argent gagné, net
 * mis en avant) et l'**à venir** (missions acceptées ou en cours → engagé mais
 * pas encore encaissé). Une note rappelle que le net = montant − commission
 * Kaikun, et un renvoi vers les missions à traiter (affectées) le cas échéant.
 */
@Component({
  selector: 'app-provider-earnings-page',
  imports: [RouterLink, BackLinkComponent],
  templateUrl: './provider-earnings-page.html',
  styleUrl: './provider-earnings-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProviderEarningsPageComponent {
  private readonly providers = inject(ProviderService);

  protected readonly loading = signal(true);
  protected readonly loadError = signal(false);
  protected readonly data = signal<ProviderEarnings | null>(null);

  /** Y a-t-il des missions à traiter (affectées) ? */
  protected readonly hasPending = computed(() => (this.data()?.missions_a_traiter ?? 0) > 0);

  constructor() {
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.loadError.set(false);
    this.providers.earnings().subscribe({
      next: (res) => {
        this.data.set(res.data);
        this.loading.set(false);
      },
      error: () => {
        this.loadError.set(true);
        this.loading.set(false);
      },
    });
  }

  /** Formate un montant FCFA. */
  protected fcfa(value: number): string | null {
    return formatFcfa(value);
  }

  /** Accord singulier / pluriel de « mission ». */
  protected missionLabel(count: number): string {
    return count > 1 ? `${count} missions` : `${count} mission`;
  }
}
