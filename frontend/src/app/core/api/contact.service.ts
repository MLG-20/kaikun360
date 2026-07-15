import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';

/**
 * Corps de `POST /contact` — miroir de `StoreContactMessageRequest`.
 * `subject` est facultatif ; les autres champs sont requis.
 */
export interface ContactMessagePayload {
  name: string;
  email: string;
  subject?: string | null;
  message: string;
}

/**
 * Envoi d'un message depuis la page Contact (F2.8.1).
 *
 * L'endpoint `POST /contact` est **public** (un prospect sans compte doit
 * pouvoir écrire) et limité en débit côté backend (anti-spam). Le message est
 * stocké puis traité par l'équipe (permission `traiter:demandes`).
 */
@Injectable({ providedIn: 'root' })
export class ContactService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** POST /contact — enregistre un message de contact (public). */
  send(payload: ContactMessagePayload): Observable<unknown> {
    const body: Record<string, unknown> = {
      name: payload.name,
      email: payload.email,
      message: payload.message,
    };
    // On ne transmet le sujet que s'il est réellement renseigné.
    if (payload.subject) {
      body['subject'] = payload.subject;
    }
    return this.http.post(`${this.api}/contact`, body);
  }
}
