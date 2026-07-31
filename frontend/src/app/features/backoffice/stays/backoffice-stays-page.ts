import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';

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
 * **F8.2.a — l'écran a maigri.** Il portait sept colonnes et tout le pilotage
 * fin : le sélecteur de ménage, les boutons de caution et leur panneau de motif.
 * Chaque ligne devenait un formulaire, et l'agent tranchait une caution sans
 * avoir sous les yeux ce qui la justifie (l'état du logement, les règlements, le
 * client). Ce pilotage a déménagé dans la **fiche du séjour**
 * (`nuitees/:id`), avec son contexte.
 *
 * Reste ici ce qu'un calendrier doit faire : montrer *qui arrive quand*, laisser
 * enregistrer l'arrivée et le départ — les deux gestes du quotidien, qui ne
 * demandent aucune information supplémentaire —, et signaler en pastilles l'état
 * du ménage et de la caution. Les voir d'un coup d'œil est le rôle de la liste ;
 * les trancher est celui du dossier.
 */
@Component({
  selector: 'app-backoffice-stays-page',
  imports: [FormsModule, RouterLink],
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

  /** Statuts de ménage possibles (table de libellés de la pastille). */
  private readonly housekeepingOptions: readonly HousekeepingOption[] = [
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

  /** Exécute une transition puis fusionne le résumé dans la ligne. */
  private run(b: StayBooking, request$: ReturnType<AdminService['stayCheckIn']>): void {
    if (this.processingId() !== null) return;

    this.processingId.set(b.booking_id);
    this.actionError.set(null);

    request$.subscribe({
      next: (summary) => {
        this.processingId.set(null);
        this.mergeSummary(summary);
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
