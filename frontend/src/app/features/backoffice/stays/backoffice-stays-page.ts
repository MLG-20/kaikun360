import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import {
  AdminService,
  CautionStatus,
  HousekeepingStatus,
  StayBooking,
  StayBookingSummary,
} from '../../../core/api/admin.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';

/** Option du sélecteur de statut de ménage. */
interface HousekeepingOption {
  value: HousekeepingStatus;
  label: string;
}

/**
 * Écran **Nuitées** du back-office (F7.2.c) — exploitation hôtelière.
 *
 * Le calendrier des séjours (`GET /admin/stays/calendar`) liste les réservations
 * de type nuitée. Depuis chaque ligne, l'agent pilote le cycle d'exploitation :
 * **enregistrer l'arrivée** (check-in), **enregistrer le départ** (check-out, qui
 * déclenche le ménage), puis **suivre le ménage** (à faire → en cours → fait).
 * Garde serveur `gerer:nuitees` : un agent sans ce droit reçoit un 403 lisible.
 *
 * **F7.3.f — la caution.** Elle était recopiée sur la réservation sans jamais être
 * suivie (statut `null` pour un séjour, là où la location de véhicule le renseigne
 * depuis B7.4) : ni retenue, ni restitution. Elle est désormais **retenue** dès la
 * réservation et se tranche ici, **après le départ** — restituée, ou conservée avec
 * un **motif obligatoire** (une caution perdue se justifie, un litige est possible).
 * Ces règles sont tenues par le serveur ; l'écran n'affiche les boutons qu'au bon
 * moment plutôt que de les dupliquer.
 */
@Component({
  selector: 'app-backoffice-stays-page',
  imports: [FormsModule],
  templateUrl: './backoffice-stays-page.html',
  styleUrl: './backoffice-stays-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeStaysPageComponent {
  private readonly admin = inject(AdminService);

  /** Séjours de la page courante. */
  protected readonly bookings = signal<StayBooking[]>([]);
  protected readonly total = signal(0);
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  /** Filtres de bornes (date d'arrivée). */
  protected from = '';
  protected to = '';

  /** Ligne en cours de transition (verrouille ses boutons). */
  protected readonly processingId = signal<number | null>(null);
  /** Message d'erreur d'une action (403 / 422). */
  protected readonly actionError = signal<string | null>(null);

  /** Ligne dont le panneau « conserver la caution » est ouvert (saisie du motif). */
  protected readonly cautionPanelId = signal<number | null>(null);
  protected cautionReason = '';

  /** Statuts de ménage possibles. */
  protected readonly housekeepingOptions: readonly HousekeepingOption[] = [
    { value: 'a_faire', label: 'À faire' },
    { value: 'en_cours', label: 'En cours' },
    { value: 'fait', label: 'Fait' },
  ];

  constructor() {
    this.load();
  }

  /** Applique les filtres de dates depuis la première page. */
  protected applyFilters(): void {
    this.page.set(1);
    this.load();
  }

  /** Charge la page courante du calendrier. */
  protected load(): void {
    this.loading.set(true);
    this.error.set(false);

    this.admin
      .staysCalendar({
        from: this.from || undefined,
        to: this.to || undefined,
        page: this.page(),
      })
      .subscribe({
        next: (paginated) => {
          this.bookings.set(paginated.data);
          this.total.set(paginated.meta.total);
          this.lastPage.set(paginated.meta.last_page);
          this.loading.set(false);
        },
        error: () => {
          this.error.set(true);
          this.loading.set(false);
        },
      });
  }

  /** Page précédente / suivante (bornée). */
  protected goTo(page: number): void {
    if (page < 1 || page > this.lastPage() || page === this.page()) return;
    this.page.set(page);
    this.load();
  }

  /** Enregistre l'arrivée d'un séjour. */
  protected checkIn(b: StayBooking): void {
    this.run(b, this.admin.stayCheckIn(b.booking_id));
  }

  /** Enregistre le départ d'un séjour (déclenche le ménage). */
  protected checkOut(b: StayBooking): void {
    this.run(b, this.admin.stayCheckOut(b.booking_id));
  }

  /** Change le statut de ménage d'un séjour. */
  protected setHousekeeping(b: StayBooking, status: HousekeepingStatus): void {
    if (status === b.housekeeping_status) return;
    this.run(b, this.admin.stayHousekeeping(b.booking_id, status));
  }

  // --- Caution (F7.3.f) -------------------------------------------------------

  /** La caution ne se tranche qu'après le départ, et une seule fois. */
  protected canSettleCaution(b: StayBooking): boolean {
    return b.caution_status === 'retenue' && b.checked_out_at !== null;
  }

  /** Restitue la caution au client (rien à justifier). */
  protected restoreCaution(b: StayBooking): void {
    this.closeCautionPanel();
    this.run(b, this.admin.stayCaution(b.booking_id, 'restituee'));
  }

  /** Ouvre / referme la saisie du motif de retenue. */
  protected toggleCautionPanel(b: StayBooking): void {
    this.actionError.set(null);
    this.cautionReason = '';
    this.cautionPanelId.update((id) => (id === b.booking_id ? null : b.booking_id));
  }

  protected closeCautionPanel(): void {
    this.cautionPanelId.set(null);
    this.cautionReason = '';
  }

  /** Conserve la caution — motif exigé (le serveur le refuse sinon). */
  protected keepCaution(b: StayBooking): void {
    const reason = this.cautionReason.trim();
    if (!reason) {
      this.actionError.set('Indiquez le motif de la retenue de la caution.');
      return;
    }
    this.run(b, this.admin.stayCaution(b.booking_id, 'perdue', reason), () =>
      this.closeCautionPanel(),
    );
  }

  /** Libellé du sort de la caution. */
  protected cautionLabel(status: CautionStatus | null): string {
    switch (status) {
      case 'retenue':
        return 'Retenue';
      case 'restituee':
        return 'Restituée';
      case 'perdue':
        return 'Conservée';
      default:
        return '—';
    }
  }

  /** Classe CSS du badge de caution. */
  protected cautionClass(status: CautionStatus | null): string {
    switch (status) {
      case 'restituee':
        return 'is-ok';
      case 'perdue':
        return 'is-off';
      case 'retenue':
        return 'is-pending';
      default:
        return '';
    }
  }

  /** Montant formaté en FCFA. */
  protected xof(value: number | null): string {
    if (!value) return '—';
    return new Intl.NumberFormat('fr-FR').format(value) + ' F';
  }

  /** Exécute une transition puis fusionne le résumé dans la ligne. */
  private run(
    b: StayBooking,
    request$: ReturnType<AdminService['stayCheckIn']>,
    after?: () => void,
  ): void {
    if (this.processingId() !== null) return;

    this.processingId.set(b.booking_id);
    this.actionError.set(null);

    request$.subscribe({
      next: (summary) => {
        this.processingId.set(null);
        this.mergeSummary(summary);
        after?.();
      },
      error: (error: HttpErrorResponse) => {
        this.processingId.set(null);
        this.actionError.set(this.messageFor(error));
      },
    });
  }

  /**
   * Met à jour la ligne concernée avec le résumé serveur (le résumé ne renvoie
   * pas le bien / les dates : on conserve ces champs de la ligne existante).
   */
  private mergeSummary(summary: StayBookingSummary): void {
    this.bookings.update((list) =>
      list.map((b) =>
        b.booking_id === summary.booking_id
          ? {
              ...b,
              status: summary.status,
              checked_in_at: summary.checked_in_at,
              checked_out_at: summary.checked_out_at,
              housekeeping_status: summary.housekeeping_status,
              caution_xof: summary.caution_xof,
              caution_status: summary.caution_status,
            }
          : b,
      ),
    );
  }

  /** Étape d'exploitation courante d'un séjour (pilote l'affichage des actions). */
  protected phase(b: StayBooking): 'a_venir' | 'sur_place' | 'parti' {
    if (b.checked_out_at) return 'parti';
    if (b.checked_in_at) return 'sur_place';
    return 'a_venir';
  }

  /** Libellé lisible d'un statut de réservation. */
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

  /** Libellé du statut de ménage. */
  protected housekeepingLabel(status: HousekeepingStatus | null): string {
    return this.housekeepingOptions.find((o) => o.value === status)?.label ?? '—';
  }

  /** Date courte (JJ mois AAAA). */
  protected shortDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }

  /** Heure courte d'un horodatage (HH:MM), ou tiret. */
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
