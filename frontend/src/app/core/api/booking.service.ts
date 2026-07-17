import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { Booking, BookingType } from '../../models/booking.model';
import { ApiEnvelope } from './api-response.model';
import { Paginated } from './pagination.model';

/**
 * Issue d'une annulation de réservation renvoyée par les endpoints de cancel
 * (`{ booking, refund }`) — miroir de la réponse des contrôleurs d'annulation
 * (expérience / véhicule).
 */
export interface CancelResult {
  booking: Booking;
  refund: {
    eligible: boolean;
    amount_xof: number;
  };
}

/**
 * Accès aux réservations du client connecté (F3.4).
 *
 * `myBookings` liste toutes les réservations tous univers confondus
 * (`GET /bookings/my`, paginé). L'**annulation** est propre à chaque univers :
 * seuls les véhicules et les expériences exposent un endpoint d'annulation
 * client, à des URL distinctes — `cancel()` route vers la bonne selon le type
 * (le drapeau `booking.cancellable` dit si le bouton doit être proposé). Auth
 * requise (Bearer posé par l'intercepteur ; un appel anonyme est détourné vers
 * la connexion).
 */
@Injectable({ providedIn: 'root' })
export class BookingService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** GET /bookings/my — les réservations de l'utilisateur connecté (paginé). */
  myBookings(page = 1): Observable<Paginated<Booking>> {
    return this.http.get<Paginated<Booking>>(`${this.api}/bookings/my`, {
      params: { page: String(page) },
    });
  }

  /**
   * Annule une réservation. L'URL dépend du type : véhicule ou expérience
   * uniquement (les autres n'ont pas d'annulation client — le composant ne
   * propose le bouton que si `booking.cancellable`). Lève une erreur pour un
   * type non annulable (garde-fou : ne devrait pas arriver côté UI).
   */
  cancel(type: BookingType, id: number): Observable<ApiEnvelope<CancelResult>> {
    const url = this.cancelUrl(type, id);
    return this.http.patch<ApiEnvelope<CancelResult>>(url, {});
  }

  /** URL d'annulation selon l'univers de la réservation. */
  private cancelUrl(type: BookingType, id: number): string {
    switch (type) {
      case 'vehicle':
        return `${this.api}/vehicles/bookings/${id}/cancel`;
      case 'experience':
        return `${this.api}/experiences/bookings/${id}/cancel`;
      default:
        throw new Error(`Type de réservation non annulable : ${type}`);
    }
  }
}
