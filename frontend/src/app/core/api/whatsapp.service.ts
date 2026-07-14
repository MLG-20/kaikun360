import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { ApiEnvelope } from './api-response.model';

/**
 * Réponse de `GET /whatsapp/link` (B16.3).
 *
 * - `url` : lien « click-to-chat » wa.me prêt à ouvrir (message déjà encodé) ;
 * - `phone` : numéro de support en chiffres uniquement (vide si non paramétré) ;
 * - `message` : le texte prérempli en clair (utile pour le déboguer / l'afficher).
 */
export interface WhatsAppLink {
  url: string;
  phone: string;
  message: string;
}

/**
 * Accès au lien WhatsApp contextuel (F2.6).
 *
 * L'utilisateur peut, depuis n'importe quelle fiche ou page, ouvrir une
 * conversation WhatsApp vers le support avec un message DÉJÀ prérempli selon la
 * page d'où il vient (le bien consulté, la référence d'une demande…). C'est le
 * backend qui compose le message et connaît le numéro officiel du support
 * (paramétré dans le back-office) : on ne code donc jamais le numéro en dur ici.
 */
@Injectable({ providedIn: 'root' })
export class WhatsAppService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /**
   * GET /whatsapp/link — construit le lien wa.me prérempli.
   *
   * @param subject   Sujet lisible (ex. le titre du bien consulté).
   * @param reference Référence éventuelle (ex. numéro d'une demande) à rappeler.
   */
  link(subject?: string | null, reference?: string | null): Observable<ApiEnvelope<WhatsAppLink>> {
    let params = new HttpParams();
    if (subject) {
      params = params.set('subject', subject);
    }
    if (reference) {
      params = params.set('reference', reference);
    }
    return this.http.get<ApiEnvelope<WhatsAppLink>>(`${this.api}/whatsapp/link`, { params });
  }
}
