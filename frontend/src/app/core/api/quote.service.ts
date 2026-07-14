import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { Quote } from '../../models/quote.model';
import { ApiEnvelope } from './api-response.model';

/**
 * Réponse possible d'un client à un devis — miroir de `RespondQuoteRequest`
 * backend : on ne peut qu'**accepter** ou **refuser** (les statuts `brouillon`
 * et `envoye` sont posés côté serveur, jamais par le client).
 */
export type QuoteDecision = 'accepte' | 'refuse';

/**
 * Accès aux devis génériques (F2.7).
 *
 * Un devis est proposé par un agent/admin sur une demande du client (couche
 * transversale B11). Le client le **consulte** puis l'**accepte ou le refuse**.
 * Il n'existe pas d'endpoint de liste côté client : on arrive sur un devis par
 * son identifiant (lien reçu par notification). Les deux endpoints exigent une
 * session ; l'autorisation fine (voir/répondre) est portée par les policies
 * `view`/`respond` côté backend (un 403 signale un devis qui n'est pas le sien).
 */
@Injectable({ providedIn: 'root' })
export class QuoteService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** GET /quotes/{id} — consulte un devis (auth requise). */
  get(id: string | number): Observable<ApiEnvelope<{ quote: Quote }>> {
    return this.http.get<ApiEnvelope<{ quote: Quote }>>(`${this.api}/quotes/${id}`);
  }

  /**
   * PATCH /quotes/{id} — accepte ou refuse un devis (auth requise).
   * Le backend refuse (422) toute réponse à un devis qui n'est pas au statut
   * `envoye` : la page ne propose donc les boutons que dans ce cas.
   */
  respond(
    id: string | number,
    status: QuoteDecision,
  ): Observable<ApiEnvelope<{ quote: Quote }>> {
    return this.http.patch<ApiEnvelope<{ quote: Quote }>>(`${this.api}/quotes/${id}`, {
      status,
    });
  }
}
