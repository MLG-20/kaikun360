import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';

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
 * Coordonnées publiques du siège (`GET /contact-info`), issues des réglables
 * back-office — jamais codées en dur côté frontend. `latitude`/`longitude`
 * alimentent la carte de la page Contact.
 */
export interface ContactInfo {
  email: string;
  phone: string;
  address: string;
  latitude: string;
  longitude: string;
  /**
   * Réseaux sociaux renseignés au back-office, par clé (`facebook`,
   * `instagram`, `tiktok`, `linkedin`, `youtube`). Les réseaux laissés vides
   * sont **absents** de l'objet : rien à filtrer côté frontend.
   */
  social: Record<string, string>;
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

  /** GET /contact-info — coordonnées publiques du siège (adresse + carte). */
  info(): Observable<ContactInfo> {
    return this.http
      .get<{ data: { contact: ContactInfo } }>(`${this.api}/contact-info`)
      .pipe(map((res) => res.data.contact));
  }

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
