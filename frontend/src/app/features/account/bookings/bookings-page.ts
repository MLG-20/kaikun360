import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { BookingService } from '../../../core/api/booking.service';
import { PageMeta } from '../../../core/api/pagination.model';
import { Booking } from '../../../models/booking.model';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';
import { HideButtonComponent } from '../../../shared/components/hide-button/hide-button';
import { BookingTone, bookingTone } from './booking-display';
import { SPACE_CONFIG } from '../../../layouts/space-layout/space.config';

/** Notice de remboursement affichée après une annulation réussie. */
interface RefundNotice {
  bookingId: number;
  eligible: boolean;
  amount: number;
}

@Component({
  selector: 'app-bookings-page',
  imports: [DatePipe, RouterLink, BackLinkComponent, HideButtonComponent],
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
  /** Espace dans lequel cet écran est monté (F8.14) : aucun lien n'est écrit en
   * dur sur `/mon-espace`, sinon monter l'écran ailleurs éjecterait
   * l'utilisateur de son espace — quand la garde de rôle ne l'y refoulerait pas. */
  protected readonly space = inject(SPACE_CONFIG);
  /** Préfixe des liens vers les réservations de CET espace. */
  protected readonly bookingsBase = this.space.basePath + '/reservations';

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

  // — Rangement dans la corbeille (F11.5) —
  /** Réservation dont le rangement est en vol (endort ce bouton, pas la liste). */
  protected readonly hidingId = signal<number | null>(null);
  /** Issue du dernier rangement (succès ou refus), affichée en tête de liste. */
  protected readonly hidden = signal<string | null>(null);

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

  // — Rangement (F11.5) —

  /**
   * Range une réservation terminée ou annulée dans la corbeille.
   *
   * ⚠️ **Rien n'est supprimé, et il faut que l'écran le dise** : une
   * réservation est un contrat. Elle quitte cette liste ; la comptabilité, les
   * reversements au partenaire et le back-office continuent de la voir. C'est
   * précisément pour cela qu'on ne peut pas offrir une vraie suppression ici.
   */
  protected hide(booking: Booking): void {
    this.hidingId.set(booking.id);
    this.hidden.set(null);

    this.bookings.hide(booking.id).subscribe({
      next: () => {
        // Retirée de l'affichage sans recharger : le serveur vient de
        // confirmer, une seconde requête ne dirait rien de plus.
        this.items.update((list) => list.filter((b) => b.id !== booking.id));
        this.hidingId.set(null);
        this.hidden.set(
          `Réservation ${booking.reference} rangée dans votre corbeille. Rien n'est supprimé : vous pouvez la restaurer.`,
        );
      },
      error: () => {
        this.hidingId.set(null);
        this.hidden.set(
          "Cette réservation ne peut pas être rangée pour le moment — elle n'est pas terminée.",
        );
      },
    });
  }

  // — Aides d'affichage —

  protected amount(value: number | null): string | null {
    return formatFcfa(value);
  }

  /** Tonalité d'affichage du statut (partagée avec l'écran de détail). */
  protected tone(status: string | null): BookingTone {
    return bookingTone(status);
  }
}
