import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';

import { AdminService, CircuitDossier } from '../../../../core/api/admin.service';
import { MediaReviewComponent } from '../../shared/media-review/media-review';
import { programmeOf } from '../circuit-programme';

/**
 * **Fiche d'un circuit** (F8.2.c) — le programme et ceux qui partent.
 *
 * L'onglet Circuits dit qu'un circuit est rempli à 12/15 ; il ne dit ni **qui
 * part**, ni ce que le circuit **promet**. Or les deux vont ensemble : un
 * circuit qui annonce « guide francophone + déjeuner » engage la plateforme
 * auprès de douze personnes nommées, joignables, dont certaines n'ont pas fini
 * de payer.
 *
 * ⚠️ Un circuit n'a **pas de date de départ** (B6.3) : sa capacité est un total
 * et le remplissage cumule toutes ses réservations. La fiche parle donc de
 * « participants », pas de « passagers d'un départ » — contrairement à la fiche
 * de trajet, dont elle partage pourtant la mise en page.
 *
 * Lecture seule : l'approbation reste à la file de validation.
 */
@Component({
  selector: 'app-backoffice-circuit-detail-page',
  imports: [RouterLink, MediaReviewComponent],
  templateUrl: './backoffice-circuit-detail-page.html',
  // Feuille COMMUNE à toutes les fiches du back-office (F8.2) : une fiche en
  // appelle une autre, elles doivent se ressembler.
  styleUrl: '../../shared/dossier.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeCircuitDetailPageComponent {
  private readonly admin = inject(AdminService);
  private readonly route = inject(ActivatedRoute);

  private readonly id = Number(this.route.snapshot.paramMap.get('id'));

  protected readonly dossier = signal<CircuitDossier | null>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  /** Le programme en clair — même lecture que l'onglet Circuits. */
  protected readonly programme = computed(() => {
    const experience = this.dossier()?.experience;
    return experience ? programmeOf(experience) : [];
  });

  /** Taux de remplissage borné, pour la jauge. */
  protected readonly fillRate = computed(() => {
    const e = this.dossier()?.experience;
    if (!e?.capacity) return 0;
    return Math.min(100, Math.round((e.seats_taken / e.capacity) * 100));
  });

  /** Participants réellement attendus (les annulés sont listés à part). */
  protected readonly expected = computed(
    () => this.dossier()?.participants.filter((p) => !p.is_cancelled) ?? [],
  );

  /** Total encore dû par les participants attendus. */
  protected readonly totalDue = computed(() =>
    this.expected().reduce((sum, p) => sum + p.remaining_xof, 0),
  );

  constructor() {
    this.load();
  }

  private load(): void {
    this.loading.set(true);
    this.error.set(false);

    this.admin.circuitDossier(this.id).subscribe({
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

  protected shortDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
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
