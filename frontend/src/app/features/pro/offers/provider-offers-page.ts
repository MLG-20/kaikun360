import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { forkJoin } from 'rxjs';

import { OfferRemoval, OfferService } from '../../../core/api/offer.service';
import { Experience } from '../../../models/experience.model';
import { MobilityService } from '../../../models/mobility-service.model';
import { Vehicle } from '../../../models/vehicle.model';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';

/** État de chargement de l'écran. */
type LoadState = 'loading' | 'ready' | 'no-profile' | 'error';

/** Les trois familles d'offre que le prestataire dépose lui-même. */
type OfferKind = 'vehicle' | 'departure' | 'experience';

/**
 * Écran « Mes offres » de l'espace prestataire (F5.6), monté sous
 * `/espace-prestataire/offres`.
 *
 * Liste les **offres réservables** déposées par le prestataire — véhicules
 * (`GET /vehicles/mine`), départs programmés (`GET /mobility-services/mine`) et
 * expériences touristiques (`GET /experiences/mine`) — avec leur **statut de
 * validation**, et donne accès aux formulaires de dépôt et d'édition. C'est le
 * geste central exigé par le CDC §5.2 / §15 (« proposer véhicule, circuit,
 * pirogue… »).
 *
 * ⚠️ **Le bloc « Départs programmés » (F8.23) comble un trou structurel** : la
 * table `mobility_services` était en lecture seule, le catalogue public
 * `/mobilite` ne pouvait donc être alimenté que par le seeder.
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
  /** Mes départs programmés (F8.23), tous statuts confondus. */
  protected readonly departures = signal<MobilityService[]>([]);
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

  /** Charge en parallèle mes véhicules, mes départs et mes expériences (1re page). */
  protected load(): void {
    this.state.set('loading');
    forkJoin({
      vehicles: this.offers.myVehicles(),
      departures: this.offers.myDepartures(),
      experiences: this.offers.myExperiences(),
    }).subscribe({
      next: ({ vehicles, departures, experiences }) => {
        this.vehicles.set(vehicles.data);
        this.departures.set(departures.data);
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

  /**
   * Ce que le statut IMPLIQUE pour la visibilité au catalogue, ou `null` quand
   * l'offre est en ligne et qu'il n'y a rien à expliquer.
   *
   * ⚠️ **Le badge seul ne suffisait pas** : « En attente de validation » décrit
   * un état administratif, il ne dit pas la conséquence — que l'offre, et donc
   * toute modification qu'on lui apporte, **n'apparaît pas au catalogue**. Un
   * prestataire qui corrige son prix puis va vérifier en ligne conclut que
   * l'enregistrement a échoué, et recommence.
   */
  protected visibilityHint(status: string | null): string | null {
    switch (status) {
      case 'publie':
        return null;
      case 'rejete':
        return "Refusée par l'équipe : corrigez l'annonce, elle repassera en validation.";
      case 'suspendu':
        return "Suspendue par l'équipe Kaikun : contactez le support.";
      case 'retire':
        return 'Retirée du catalogue à votre demande.';
      default:
        return "Pas encore visible au catalogue : un agent doit valider l'annonce. "
          + 'Vos modifications sont bien enregistrées et seront publiées avec elle.';
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
  protected confirmRemove(kind: OfferKind, id: number): void {
    const cle = `${kind}-${id}`;
    if (this.removing()) {
      return;
    }
    this.removing.set(cle);
    this.removalError.set(null);

    const call$ =
      kind === 'vehicle'
        ? this.offers.deleteVehicle(id)
        : kind === 'departure'
          ? this.offers.deleteDeparture(id)
          : this.offers.deleteExperience(id);

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
  private announce(kind: OfferKind, resultat: OfferRemoval): void {
    const quoi = { vehicle: 'Le véhicule', departure: 'Le départ', experience: 'Le circuit' }[kind];

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

  /** Libellé d'un départ : son itinéraire, ce que le client lit au catalogue. */
  protected departureName(d: MobilityService): string {
    return `${d.departure} → ${d.destination}`;
  }

  /** Date et heure du départ, en clair (« 14/08/2026 à 06:30 »). */
  protected departureWhen(d: MobilityService): string {
    if (!d.departure_at) {
      return 'Date non renseignée';
    }
    const date = new Date(d.departure_at);
    const p = (n: number) => String(n).padStart(2, '0');
    return (
      `${p(date.getDate())}/${p(date.getMonth() + 1)}/${date.getFullYear()} ` +
      `à ${p(date.getHours())}:${p(date.getMinutes())}`
    );
  }

  /**
   * Ce départ est-il passé ?
   *
   * ⚠️ Signalé à part du statut, parce qu'aucun statut ne le dit : un départ
   * **publié** dont l'heure est passée reste « Publié » et n'est pourtant plus
   * réservable. Sans cette mention, le prestataire voit une offre en ligne qui
   * ne lui rapporte plus rien et n'a aucune raison de la retirer.
   */
  protected isPast(d: MobilityService): boolean {
    return d.departure_at !== null && d.departure_at !== undefined
      ? new Date(d.departure_at).getTime() < Date.now()
      : false;
  }
}
