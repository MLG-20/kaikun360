import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { BookingService } from '../../../core/api/booking.service';
import { PageMeta } from '../../../core/api/pagination.model';
import { Booking } from '../../../models/booking.model';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';

/** Notice de remboursement affichée après une annulation réussie. */
interface RefundNotice {
  bookingId: number;
  eligible: boolean;
  amount: number;
}

@Component({
  selector: 'app-bookings-page',
  imports: [DatePipe, RouterLink],
  templateUrl: './bookings-page.html',
  styleUrl: './bookings-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
/**
 * Écran « Mes réservations » de l'espace client (F3.4), monté sous
 * `/mon-espace/reservations`. Liste paginée des réservations du client, tous
 * univers confondus (`GET /bookings/my`, plus récentes d'abord).
 *
 * Chaque réservation est une carte : élément réservé (nuitée / véhicule /
 * expérience / trajet), dates, voyageurs, montant, caution et statut. Le client
 * peut **annuler** une réservation lorsque le backend le permet (`cancellable` —
 * véhicules et expériences non encore annulés) ; l'annulation route vers
 * l'endpoint propre à l'univers et affiche l'éligibilité au remboursement.
 */
export class BookingsPageComponent {
  private readonly bookings = inject(BookingService);

  // — État de l'écran —
  protected readonly loading = signal(true);
  protected readonly loadError = signal(false);
  protected readonly items = signal<Booking[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);

  // — Annulation —
  /** Réservation dont la confirmation d'annulation est affichée. */
  protected readonly confirmId = signal<number | null>(null);
  /** Réservation dont l'annulation est en cours (requête en vol). */
  protected readonly busyId = signal<number | null>(null);
  protected readonly cancelError = signal<string | null>(null);
  /** Résultat de remboursement de la dernière annulation réussie. */
  protected readonly refund = signal<RefundNotice | null>(null);

  protected readonly hasPrev = computed(() => (this.meta()?.current_page ?? 1) > 1);
  protected readonly hasNext = computed(() => {
    const m = this.meta();
    return !!m && m.current_page < m.last_page;
  });

  constructor() {
    this.load(1);
  }

  /** Charge une page de réservations (remplace la liste affichée). */
  protected load(page: number): void {
    this.loading.set(true);
    this.loadError.set(false);
    this.confirmId.set(null);
    this.refund.set(null);
    this.cancelError.set(null);
    this.bookings.myBookings(page).subscribe({
      next: (res) => {
        this.items.set(res.data);
        this.meta.set(res.meta);
        this.loading.set(false);
        if (typeof window !== 'undefined') {
          window.scrollTo({ top: 0, behavior: 'smooth' });
        }
      },
      error: () => {
        this.loading.set(false);
        this.loadError.set(true);
      },
    });
  }

  protected prev(): void {
    if (this.hasPrev()) {
      this.load((this.meta()?.current_page ?? 2) - 1);
    }
  }

  protected next(): void {
    if (this.hasNext()) {
      this.load((this.meta()?.current_page ?? 0) + 1);
    }
  }

  // — Annulation —

  /** Ouvre la demande de confirmation pour une réservation donnée. */
  protected askCancel(booking: Booking): void {
    this.cancelError.set(null);
    this.confirmId.set(booking.id);
  }

  /** Referme la demande de confirmation sans annuler. */
  protected dismissCancel(): void {
    this.confirmId.set(null);
  }

  /** Confirme et exécute l'annulation via l'endpoint propre à l'univers. */
  protected confirmCancel(booking: Booking): void {
    this.busyId.set(booking.id);
    this.cancelError.set(null);
    this.bookings.cancel(booking.type, booking.id).subscribe({
      next: (res) => {
        // Remplace la réservation par sa version fraîche (statut annulé,
        // cancellable=false) et affiche le résultat de remboursement.
        this.items.update((list) =>
          list.map((b) => (b.id === booking.id ? res.data.booking : b)),
        );
        this.refund.set({
          bookingId: booking.id,
          eligible: res.data.refund.eligible,
          amount: res.data.refund.amount_xof,
        });
        this.busyId.set(null);
        this.confirmId.set(null);
      },
      error: () => {
        this.busyId.set(null);
        this.cancelError.set(
          "L'annulation n'a pas pu aboutir. Merci de réessayer plus tard.",
        );
      },
    });
  }

  // — Aides d'affichage —

  protected amount(value: number | null): string | null {
    return formatFcfa(value);
  }

  /**
   * Tonalité d'affichage du statut (regroupe les variantes d'annulation) pour
   * teinter la pastille via `data-tone`.
   */
  protected tone(status: string | null): 'pending' | 'ok' | 'active' | 'done' | 'cancelled' {
    switch (status) {
      case 'en_attente':
        return 'pending';
      case 'confirmee':
        return 'ok';
      case 'en_cours':
        return 'active';
      case 'terminee':
        return 'done';
      default:
        return 'cancelled'; // annulee_client / annulee_prestataire / annulee_admin
    }
  }
}
