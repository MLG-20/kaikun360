import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';

import { AdminService, VehicleDossier } from '../../../../core/api/admin.service';
import { MediaReviewComponent } from '../../shared/media-review/media-review';
import {
  ComplianceCheck,
  complianceChecks,
  complianceClass,
  complianceLabel,
} from '../vehicle-compliance';

/**
 * **Fiche d'un véhicule** (F8.2.b) — la flotte, une unité à la fois.
 *
 * L'onglet Flotte signale qu'un véhicule n'est pas conforme ; il ne dit pas quoi
 * en faire. Or la décision dépend de ce que la ligne ne montre pas : ce véhicule
 * **roule-t-il déjà** ? qui appeler ? à quoi ressemble-t-il ?
 *
 * La fiche répond en rassemblant le contrôle **pièce par pièce** (avec la même
 * grille que la liste, partagée dans `vehicle-compliance.ts`), le prestataire
 * joignable, les photos, les locations et les **départs programmés** qu'il
 * porte. Ce dernier point est le vrai enjeu : suspendre un véhicule qui porte
 * trois départs pleins n'est pas le même geste que suspendre un véhicule au
 * repos — d'où le compteur d'engagements à venir, mis en avant.
 *
 * Lecture seule, comme l'écran dont elle est le détail : la décision de
 * publication reste à la file de validation, qui la trace.
 */
@Component({
  selector: 'app-backoffice-vehicle-detail-page',
  imports: [RouterLink, MediaReviewComponent],
  templateUrl: './backoffice-vehicle-detail-page.html',
  // Feuille COMMUNE à toutes les fiches du back-office (F8.2) : une fiche en
  // appelle une autre, elles doivent se ressembler.
  styleUrl: '../../shared/dossier.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeVehicleDetailPageComponent {
  private readonly admin = inject(AdminService);
  private readonly route = inject(ActivatedRoute);

  private readonly id = Number(this.route.snapshot.paramMap.get('id'));

  protected readonly dossier = signal<VehicleDossier | null>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  /** Intitulé lisible (marque + modèle, ou repli sur la catégorie). */
  protected readonly label = computed(() => {
    const v = this.dossier()?.vehicle;
    if (!v) return '';
    return [v.brand, v.model].filter(Boolean).join(' ') || (v.type_label ?? 'Véhicule');
  });

  /** Le contrôle de conformité, point par point. */
  protected readonly checks = computed<ComplianceCheck[]>(() => {
    const v = this.dossier()?.vehicle;
    return v ? complianceChecks(v) : [];
  });

  protected readonly verdictLabel = computed(() => {
    const v = this.dossier()?.vehicle;
    return v ? complianceLabel(v) : '';
  });

  protected readonly verdictClass = computed(() => {
    const v = this.dossier()?.vehicle;
    return v ? complianceClass(v) : '';
  });

  /**
   * Départs **encore devant nous** : ce qu'une suspension du véhicule ferait
   * tomber. Un départ passé n'engage plus rien, il reste dans l'historique.
   */
  protected readonly upcomingTrips = computed(
    () => this.dossier()?.trips.filter((t) => t.is_upcoming) ?? [],
  );

  /** Places déjà vendues sur ces départs à venir — le coût réel d'un retrait. */
  protected readonly seatsAtStake = computed(() =>
    this.upcomingTrips().reduce((sum, t) => sum + t.seats_taken, 0),
  );

  constructor() {
    this.load();
  }

  private load(): void {
    this.loading.set(true);
    this.error.set(false);

    this.admin.vehicleDossier(this.id).subscribe({
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
      case 'annulee_prestataire':
      case 'annulee_admin':
        return 'Annulée';
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
