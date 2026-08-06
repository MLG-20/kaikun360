import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { forkJoin } from 'rxjs';

import { OfferRemoval, OfferService } from '../../../core/api/offer.service';
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

  // --- Retrait d'une offre (F8.19) -------------------------------------------

  /**
   * Offre dont le retrait attend confirmation, sous la forme `vehicle-12`.
   *
   * ⚠️ **Confirmation en deux temps DANS la ligne**, et non `window.confirm` :
   * la boîte native n'existe pas au rendu serveur, ne se traduit pas, et surtout
   * ne peut pas expliquer la conséquence — or elle diffère selon l'offre
   * (suppression réelle, ou retrait avec conservation de l'historique).
   */
  protected readonly pendingRemoval = signal<string | null>(null);

  /** Retrait en cours (désactive les boutons de la ligne concernée). */
  protected readonly removing = signal<string | null>(null);

  /** Ce que le serveur a répondu au dernier retrait, à afficher au prestataire. */
  protected readonly removalNotice = signal<string | null>(null);

  protected readonly removalError = signal<string | null>(null);

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
      // F8.19 — retrait VOLONTAIRE : ni une sanction, ni un rejet.
      case 'retire':
        return 'is-withdrawn';
      default:
        return 'is-pending';
    }
  }

  /** Demande confirmation avant de retirer (ou annule la demande en cours). */
  protected askRemove(cle: string | null): void {
    this.removalError.set(null);
    this.removalNotice.set(null);
    this.pendingRemoval.set(cle);
  }

  /**
   * Retire l'offre. **Le serveur décide** s'il supprime réellement ou s'il
   * conserve : l'écran ne rejoue pas la règle, il annonce le résultat.
   */
  protected confirmRemove(kind: 'vehicle' | 'experience', id: number): void {
    const cle = `${kind}-${id}`;
    if (this.removing()) {
      return;
    }
    this.removing.set(cle);
    this.removalError.set(null);

    const call$ =
      kind === 'vehicle' ? this.offers.deleteVehicle(id) : this.offers.deleteExperience(id);

    call$.subscribe({
      next: (env) => {
        this.removing.set(null);
        this.pendingRemoval.set(null);
        this.announce(kind, env.data);
        // On relit la liste plutôt que de retirer la ligne à la main : une offre
        // conservée doit RESTER visible, avec son nouveau statut « Retiré ».
        this.load();
      },
      error: () => {
        this.removing.set(null);
        this.pendingRemoval.set(null);
        this.removalError.set("Le retrait n'a pas abouti. Réessayez dans un instant.");
      },
    });
  }

  /** Traduit la réponse du serveur en une phrase pour le prestataire. */
  private announce(kind: 'vehicle' | 'experience', resultat: OfferRemoval): void {
    const quoi = kind === 'vehicle' ? 'Le véhicule' : 'Le circuit';

    this.removalNotice.set(
      resultat.deleted
        ? `${quoi} a été supprimé, avec ses photos.`
        : `${quoi} a été retiré du catalogue. ${resultat.reason ?? ''}`.trim(),
    );
  }

  /** Libellé « marque modèle » d'un véhicule, avec repli sur le type. */
  protected vehicleName(v: Vehicle): string {
    return [v.brand, v.model].filter(Boolean).join(' ') || v.type_label || 'Véhicule';
  }
}
