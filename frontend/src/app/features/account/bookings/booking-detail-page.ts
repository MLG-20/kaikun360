import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { of } from 'rxjs';
import { catchError, map, switchMap, tap } from 'rxjs/operators';

import { BookingService } from '../../../core/api/booking.service';
import { Booking } from '../../../models/booking.model';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';
import { BookingTone, bookingTone } from './booking-display';

/** État de chargement de l'écran. */
type LoadState = 'loading' | 'ready' | 'notfound' | 'forbidden' | 'failed';

@Component({
  selector: 'app-booking-detail-page',
  imports: [DatePipe, RouterLink, BackLinkComponent],
  templateUrl: './booking-detail-page.html',
  styleUrl: './booking-detail-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
/**
 * Détail d'une réservation de l'espace client (F3.4), monté sous
 * `/mon-espace/reservations/:id`. Atteint en cliquant une carte depuis « Mes
 * réservations ».
 *
 * Charge la réservation (`GET /bookings/{id}`, réservée au titulaire) et en
 * présente le récapitulatif complet — univers, élément réservé, dates,
 * voyageurs, montant, caution, statut teinté. Un bouton « ← Mes réservations »
 * ramène toujours à la liste. Une réservation qui n'appartient pas à
 * l'utilisateur renvoie 403 (état « accès refusé »). **L'annulation reste sur la
 * liste** (là où l'action inline est déjà câblée) — cet écran est en lecture seule.
 */
export class BookingDetailPageComponent {
  private readonly bookings = inject(BookingService);
  private readonly route = inject(ActivatedRoute);

  // — État de l'écran —
  protected readonly state = signal<LoadState>('loading');
  protected readonly booking = signal<Booking | null>(null);

  /**
   * Déclenche le chargement dès que l'identifiant est connu. `switchMap` annule
   * une requête précédente si l'on change de réservation.
   */
  private readonly loader = toSignal(
    this.route.paramMap.pipe(
      map((params) => params.get('id')),
      switchMap((id) => {
        this.state.set('loading');
        this.booking.set(null);
        if (!id) {
          this.state.set('notfound');
          return of(null);
        }
        return this.bookings.get(id).pipe(
          tap((env) => {
            this.booking.set(env.data.booking);
            this.state.set('ready');
          }),
          catchError((err: { status?: number }) => {
            this.state.set(this.stateForError(err?.status));
            return of(null);
          }),
        );
      }),
    ),
  );

  /** Tonalité d'affichage du statut (partagée avec la liste). */
  protected tone(status: string | null): BookingTone {
    return bookingTone(status);
  }

  /** Montant formaté en FCFA (ou null si non renseigné). */
  protected amount(value: number | null): string | null {
    return formatFcfa(value);
  }

  /** Traduit un code HTTP d'erreur en état d'affichage. */
  private stateForError(status?: number): LoadState {
    if (status === 404) {
      return 'notfound';
    }
    if (status === 403) {
      return 'forbidden';
    }
    return 'failed';
  }
}
