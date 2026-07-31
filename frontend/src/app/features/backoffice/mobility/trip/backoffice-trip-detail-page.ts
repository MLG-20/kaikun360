import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';

import { AdminService, TripDossier } from '../../../../core/api/admin.service';

/**
 * **Fiche d'un départ programmé** (F8.2.b) — la liste des passagers.
 *
 * L'onglet Trajets donne le remplissage d'un départ (« 12 / 15 ») ; cette fiche
 * donne **qui** sont ces douze. C'est la différence entre superviser et
 * exploiter : un départ qui approche se prépare avec les noms, les places et de
 * quoi joindre chacun — et avec la liste de ceux qui n'ont pas fini de payer.
 *
 * Deux points d'attention que la liste ne pouvait pas porter :
 *   - les réservations **annulées** restent affichées, barrées : une annulation
 *     de la veille explique un départ soudain à moitié vide ;
 *   - la **capacité du véhicule affecté** est confrontée à celle du trajet, pour
 *     que personne ne découvre au moment de l'embarquement qu'on a vendu plus de
 *     places qu'il n'y a de sièges.
 */
@Component({
  selector: 'app-backoffice-trip-detail-page',
  imports: [RouterLink],
  templateUrl: './backoffice-trip-detail-page.html',
  // Feuille COMMUNE à toutes les fiches du back-office (F8.2) : une fiche en
  // appelle une autre, elles doivent se ressembler.
  styleUrl: '../../shared/dossier.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeTripDetailPageComponent {
  private readonly admin = inject(AdminService);
  private readonly route = inject(ActivatedRoute);

  private readonly id = Number(this.route.snapshot.paramMap.get('id'));

  protected readonly dossier = signal<TripDossier | null>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  /** Taux de remplissage borné, pour la jauge. */
  protected readonly fillRate = computed(() => {
    const t = this.dossier()?.trip;
    if (!t?.capacity) return 0;
    return Math.min(100, Math.round((t.seats_taken / t.capacity) * 100));
  });

  /** Passagers effectivement attendus (les annulés sont listés à part). */
  protected readonly expected = computed(
    () => this.dossier()?.passengers.filter((p) => !p.is_cancelled) ?? [],
  );

  /** Total encore dû par les passagers attendus — à réclamer avant le départ. */
  protected readonly totalDue = computed(() =>
    this.expected().reduce((sum, p) => sum + p.remaining_xof, 0),
  );

  /**
   * Places vendues au-delà de ce que le véhicule affecté peut porter.
   *
   * Le trajet et le véhicule ont chacun leur capacité, et rien n'oblige la
   * seconde à couvrir la première (un véhicule peut être réaffecté après coup).
   * Découvrir l'écart à l'embarquement serait le pire moment.
   */
  protected readonly overbooked = computed(() => {
    const t = this.dossier()?.trip;
    const seats = t?.vehicle?.capacity;
    if (!t || !seats) return 0;
    return Math.max(0, t.seats_taken - seats);
  });

  /** Départ déjà passé : la fiche devient un compte rendu, pas une préparation. */
  protected readonly isPast = computed(() => {
    const at = this.dossier()?.trip.departure_at;
    return at ? new Date(at).getTime() < Date.now() : false;
  });

  constructor() {
    this.load();
  }

  private load(): void {
    this.loading.set(true);
    this.error.set(false);

    this.admin.tripDossier(this.id).subscribe({
      next: (dossier) => {
        this.dossier.set(dossier);
        this.loading.set(false);
      },
      error: () => {
        this.error.set(true);
        this.loading.set(false);
      },
    });
  }

  /** Classe CSS du badge de statut (mêmes codes que l'écran Catalogues). */
  protected statusClass(status: string | null): string {
    switch (status) {
      case 'publie':
        return 'is-ok';
      case 'en_attente_validation':
        return 'is-pending';
      case 'suspendu':
        return 'is-warn';
      case 'rejete':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  /** Libellé lisible d'un statut de réservation. */
  protected bookingStatusLabel(status: string): string {
    switch (status) {
      case 'en_attente':
        return 'En attente';
      case 'confirmee':
        return 'Confirmée';
      case 'en_cours':
        return 'En cours';
      case 'terminee':
        return 'Terminée';
      case 'annulee_client':
        return 'Annulée (client)';
      case 'annulee_prestataire':
        return 'Annulée (prestataire)';
      case 'annulee_admin':
        return 'Annulée (Kaikun)';
      default:
        return status;
    }
  }

  protected xof(value: number | null | undefined): string {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('fr-FR').format(value) + ' F';
  }

  protected dateTime(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }
}
