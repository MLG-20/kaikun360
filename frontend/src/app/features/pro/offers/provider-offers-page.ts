import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { forkJoin } from 'rxjs';

import { OfferService } from '../../../core/api/offer.service';
import { Experience } from '../../../models/experience.model';
import { Vehicle } from '../../../models/vehicle.model';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';

/** État de chargement de l'écran. */
type LoadState = 'loading' | 'ready' | 'no-profile' | 'error';

/**
 * Écran « Mes offres » de l'espace prestataire (F5.6), monté sous
 * `/espace-prestataire/offres`.
 *
 * Liste les **offres réservables** déposées par le prestataire — véhicules
 * (`GET /vehicles/mine`) et expériences touristiques (`GET /experiences/mine`) —
 * avec leur **statut de validation**, et donne accès aux formulaires de dépôt
 * et d'édition. C'est le geste central exigé par le CDC §5.2 / §15 (« proposer
 * véhicule, circuit, pirogue… »).
 *
 * Le dépôt exige un **profil prestataire validé** : un 403 sur la liste (compte
 * non éligible) bascule l'écran en « no-profile » avec renvoi vers l'inscription.
 */
@Component({
  selector: 'app-provider-offers-page',
  imports: [RouterLink, BackLinkComponent],
  templateUrl: './provider-offers-page.html',
  styleUrl: './provider-offers-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProviderOffersPageComponent {
  private readonly offers = inject(OfferService);

  protected readonly state = signal<LoadState>('loading');
  protected readonly vehicles = signal<Vehicle[]>([]);
  protected readonly experiences = signal<Experience[]>([]);

  /** Formatage FCFA lisible (mutualisé avec le catalogue public). */
  protected readonly fcfa = formatFcfa;

  constructor() {
    this.load();
  }

  /** Charge en parallèle mes véhicules et mes expériences (première page). */
  protected load(): void {
    this.state.set('loading');
    forkJoin({
      vehicles: this.offers.myVehicles(),
      experiences: this.offers.myExperiences(),
    }).subscribe({
      next: ({ vehicles, experiences }) => {
        this.vehicles.set(vehicles.data);
        this.experiences.set(experiences.data);
        this.state.set('ready');
      },
      error: (err: { status?: number }) => {
        // 403 = pas de profil prestataire validé → on oriente vers l'inscription.
        this.state.set(err?.status === 403 ? 'no-profile' : 'error');
      },
    });
  }

  /** Classe CSS du badge de statut (mêmes valeurs que l'enum backend). */
  protected statusClass(status: string | null): string {
    switch (status) {
      case 'publie':
        return 'is-published';
      case 'rejete':
        return 'is-rejected';
      case 'suspendu':
        return 'is-suspended';
      default:
        return 'is-pending';
    }
  }

  /** Libellé « marque modèle » d'un véhicule, avec repli sur le type. */
  protected vehicleName(v: Vehicle): string {
    return [v.brand, v.model].filter(Boolean).join(' ') || v.type_label || 'Véhicule';
  }
}
