import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { environment } from '../../../environments/environment';
import { Booking, BookingType } from '../../models/booking.model';
import { ApiEnvelope } from './api-response.model';
import { Paginated } from './pagination.model';

/**
 * Corps de `POST /stays/{id}/bookings` — miroir de `StoreStayBookingRequest`.
 * Dates au format `YYYY-MM-DD` ; la date de départ est **exclusive** (on ne
 * dort pas la nuit du départ), c'est ce qui fait le nombre de nuits.
 */
export interface CreateStayBookingPayload {
  start_date: string;
  end_date: string;
  guests: number;
}

/**
 * Corps de `POST /vehicles/{id}/bookings`. Location à la **journée** : les deux
 * bornes sont incluses (rendre et relouer le même jour, c'est la même journée
 * de mise à disposition), une location d'un seul jour est donc permise.
 */
export interface CreateVehicleBookingPayload {
  start_date: string;
  end_date: string;
  guests?: number;
}

/**
 * Corps de `POST /experiences/{id}/bookings`. Un circuit n'a **pas de date de
 * fin** : sa durée lui appartient, le client ne choisit que son jour de départ.
 */
export interface CreateExperienceBookingPayload {
  start_date: string;
  guests: number;
}

/**
 * Corps de `POST /mobility-services/{id}/bookings`. Un trajet est déjà daté :
 * on n'y réserve que des **places**.
 */
export interface CreateMobilityBookingPayload {
  guests: number;
}

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

  /**
   * POST /stays/{id}/bookings — **réserve une nuitée** (F8.10).
   *
   * ⚠️ Cet endpoint existait depuis B3.3 et **aucun écran ne l'appelait** : la
   * fiche d'une nuitée n'offrait qu'un formulaire de *demande* (`POST /requests`),
   * qui crée un prospect et non un contrat. Le client croyait avoir réservé et
   * ne trouvait rien dans « Mes réservations ».
   *
   * Le serveur reste seul juge et refuse en **422** ce qui doit l'être :
   * capacité dépassée, séjour trop court ou trop long, créneau déjà pris. Rien
   * de tout cela n'est rejoué ici — l'écran se contente d'afficher le message
   * renvoyé, qui est déjà rédigé pour un client.
   *
   * La réservation naît **en attente de paiement**, avec sa commission figée et
   * sa caution retenue ; c'est le règlement qui la confirme ensuite.
   *
   * Exige un compte connecté **et vérifié** (middleware `verified.account`,
   * sinon 403).
   */
  createStayBooking(stayId: number, payload: CreateStayBookingPayload): Observable<Booking> {
    return this.http
      .post<ApiEnvelope<{ booking: Booking }>>(`${this.api}/stays/${stayId}/bookings`, payload)
      .pipe(map((response) => response.data.booking));
  }

  /**
   * POST /vehicles/{id}/bookings — **loue un véhicule** (F8.10).
   *
   * Le serveur refuse en 422 une période déjà louée — contrôle ajouté dans la
   * même tranche : il manquait, et deux clients pouvaient repartir avec le même
   * 4×4 le même jour.
   */
  createVehicleBooking(
    vehicleId: number,
    payload: CreateVehicleBookingPayload,
  ): Observable<Booking> {
    return this.http
      .post<ApiEnvelope<{ booking: Booking }>>(
        `${this.api}/vehicles/${vehicleId}/bookings`,
        payload,
      )
      .pipe(map((response) => response.data.booking));
  }

  /**
   * POST /bookings/{id}/hide — range une réservation dans la corbeille (F11.5).
   *
   * ⚠️ **Ne supprime rien, et ne peut pas le faire** : une réservation est un
   * contrat entre le client, Kaikun et un partenaire. Elle quitte la seule
   * liste du client ; la comptabilité et les reversements continuent de la
   * voir. Le serveur refuse (422) tout ce qui n'est pas TERMINÉ ou ANNULÉ —
   * c'est ce que dit déjà le drapeau `hideable` de la ressource.
   */
  hide(id: number): Observable<ApiEnvelope<{ message: string }>> {
    return this.http.post<ApiEnvelope<{ message: string }>>(
      `${this.api}/bookings/${id}/hide`,
      {},
    );
  }

  /**
   * POST /experiences/{id}/bookings — **réserve des places sur un circuit**
   * (F8.10). Le serveur contrôle les places restantes et refuse en 422 en
   * annonçant combien il en reste.
   */
  createExperienceBooking(
    experienceId: number,
    payload: CreateExperienceBookingPayload,
  ): Observable<Booking> {
    return this.http
      .post<ApiEnvelope<{ booking: Booking }>>(
        `${this.api}/experiences/${experienceId}/bookings`,
        payload,
      )
      .pipe(map((response) => response.data.booking));
  }

  /**
   * POST /mobility-services/{id}/bookings — **réserve des places sur un
   * départ programmé** (F8.10). Même contrôle de remplissage que les circuits.
   */
  createMobilityBooking(
    serviceId: number,
    payload: CreateMobilityBookingPayload,
  ): Observable<Booking> {
    return this.http
      .post<ApiEnvelope<{ booking: Booking }>>(
        `${this.api}/mobility-services/${serviceId}/bookings`,
        payload,
      )
      .pipe(map((response) => response.data.booking));
  }

  /** GET /bookings/my — les réservations de l'utilisateur connecté (paginé). */
  myBookings(page = 1): Observable<Paginated<Booking>> {
    return this.http.get<Paginated<Booking>>(`${this.api}/bookings/my`, {
      params: { page: String(page) },
    });
  }

  /**
   * GET /bookings/{id} — détail d'UNE de mes réservations (F3.4).
   *
   * Réservé au titulaire (403 sinon, détourné par l'`errorInterceptor`).
   * Alimente l'écran de détail atteint en cliquant une carte depuis « Mes
   * réservations ». Auth requise (Bearer posé par l'intercepteur).
   */
  get(id: number | string): Observable<ApiEnvelope<{ booking: Booking }>> {
    return this.http.get<ApiEnvelope<{ booking: Booking }>>(
      `${this.api}/bookings/${id}`,
    );
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
