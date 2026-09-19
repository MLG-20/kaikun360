import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';

import {
  AdminService,
  HousekeepingStatus,
  StayBookingSummary,
  StayDossier,
} from '../../../../core/api/admin.service';
import { ValidationErrorBody } from '../../../../core/api/api-response.model';
import { MediaReviewComponent } from '../../shared/media-review/media-review';

/** Option du sélecteur de statut de ménage (même table que le calendrier). */
interface HousekeepingOption {
  value: HousekeepingStatus;
  label: string;
}

/**
 * **Fiche d'un séjour** (F8.2.a) — le dossier complet d'une nuitée.
 *
 * Le calendrier des nuitées est une vue d'exploitation : il dit *qui arrive
 * quand*, et s'arrête là. Dès qu'un client appelle — « où en est ma caution ? »,
 * « j'ai déjà versé l'acompte » —, l'agent devait sauter d'écran en écran
 * (Paiements, Comptes, Catalogues) sans jamais voir le séjour d'un seul tenant.
 *
 * Cette page rassemble les quatre faces d'un séjour : **le séjour** (dates,
 * nuits, phase), **le logement** et son hôte joignable, **le client et
 * l'argent** (encaissé / reste à payer, paiements un par un), et **la trace** —
 * le journal d'audit.
 *
 * Les gestes d'exploitation (arrivée, départ, ménage) sont pilotables
 * ici comme depuis la liste : c'est la même API, aux mêmes règles serveur.
 * L'agent qui ouvre un dossier pour comprendre n'a pas à revenir en arrière
 * pour agir.
 */
@Component({
  selector: 'app-backoffice-stay-detail-page',
  imports: [FormsModule, RouterLink, MediaReviewComponent],
  templateUrl: './backoffice-stay-detail-page.html',
  // Feuille COMMUNE à toutes les fiches du back-office (F8.2) : une fiche en
  // appelle une autre, elles doivent se ressembler.
  styleUrl: '../../shared/dossier.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeStayDetailPageComponent {
  private readonly admin = inject(AdminService);
  private readonly route = inject(ActivatedRoute);

  /** Identifiant de la réservation, lu dans l'URL. */
  private readonly id = Number(this.route.snapshot.paramMap.get('id'));

  protected readonly dossier = signal<StayDossier | null>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  /** Action en cours (verrouille tous les boutons du dossier). */
  protected readonly processing = signal(false);
  protected readonly actionError = signal<string | null>(null);

  protected readonly housekeepingOptions: readonly HousekeepingOption[] = [
    { value: 'a_faire', label: 'À faire' },
    { value: 'en_cours', label: 'En cours' },
    { value: 'fait', label: 'Fait' },
  ];

  /** Étape d'exploitation courante (pilote l'affichage des actions). */
  protected readonly phase = computed<'a_venir' | 'sur_place' | 'parti'>(() => {
    const b = this.dossier()?.booking;
    if (b?.checked_out_at) return 'parti';
    if (b?.checked_in_at) return 'sur_place';
    return 'a_venir';
  });

  /** Localisation lisible du logement (les niveaux vides sont écartés). */
  protected readonly location = computed(() => {
    const stay = this.dossier()?.stay;
    if (!stay) return '';
    return [stay.address, stay.commune, stay.department, stay.region].filter(Boolean).join(' · ');
  });

  constructor() {
    this.load();
  }

  /** Charge le dossier complet. */
  private load(): void {
    this.loading.set(true);
    this.error.set(false);

    this.admin.stayDossier(this.id).subscribe({
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

  // --- Gestes d'exploitation --------------------------------------------------

  protected checkIn(): void {
    this.run(this.admin.stayCheckIn(this.id));
  }

  protected checkOut(): void {
    this.run(this.admin.stayCheckOut(this.id));
  }

  protected setHousekeeping(status: HousekeepingStatus): void {
    if (status === this.dossier()?.booking.housekeeping_status) return;
    this.run(this.admin.stayHousekeeping(this.id, status));
  }

  /**
   * Exécute une transition, fusionne le résumé serveur dans le dossier ouvert.
   */
  private run(request$: ReturnType<AdminService['stayCheckIn']>): void {
    if (this.processing()) return;

    this.processing.set(true);
    this.actionError.set(null);

    request$.subscribe({
      next: (summary) => {
        this.processing.set(false);
        this.mergeSummary(summary);
      },
      error: (error: HttpErrorResponse) => {
        this.processing.set(false);
        this.actionError.set(this.messageFor(error));
      },
    });
  }

  /** Applique au dossier ouvert les champs renvoyés par la transition. */
  private mergeSummary(summary: StayBookingSummary): void {
    this.dossier.update((current) =>
      current === null
        ? current
        : {
            ...current,
            booking: {
              ...current.booking,
              status: summary.status,
              checked_in_at: summary.checked_in_at,
              checked_out_at: summary.checked_out_at,
              housekeeping_status: summary.housekeeping_status,
            },
          },
    );
  }

  // --- Libellés ---------------------------------------------------------------

  protected statusLabel(status: string): string {
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

  protected housekeepingLabel(status: HousekeepingStatus | null): string {
    return this.housekeepingOptions.find((o) => o.value === status)?.label ?? '—';
  }

  /** Montant formaté en FCFA (0 reste affiché : « 0 F » n'est pas « — »). */
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

  protected shortTime(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('fr-FR', {
      day: '2-digit',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  private messageFor(error: HttpErrorResponse): string {
    if (error.status === 403) {
      return "Vous n'avez pas le droit de gérer les nuitées. Demandez la délégation à un administrateur.";
    }
    if (error.status === 422) {
      const body = error.error as ValidationErrorBody | null;
      return body?.message ?? 'Transition impossible dans cet état.';
    }
    return 'Opération impossible pour le moment. Réessayez.';
  }
}
