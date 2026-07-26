import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import {
  TeamBuildingNeeds,
  TeamBuildingQuote,
  TeamBuildingRequest,
} from '../../models/team-building.model';
import { ApiEnvelope } from './api-response.model';
import { Paginated } from './pagination.model';

/**
 * Corps de `POST /team-building-requests` — miroir de
 * `StoreTeamBuildingRequestRequest`. `participants`, `city`, `start_date` et
 * `end_date` sont requis ; le reste est facultatif.
 */
export interface CreateTeamBuildingRequestPayload {
  participants: number;
  city: string;
  start_date: string;
  end_date: string;
  budget_xof?: number | null;
  needs?: TeamBuildingNeeds | null;
  description?: string | null;
}

/**
 * Accès aux demandes/devis de team building de l'espace **entreprise** (F6).
 *
 * Une entreprise dépose une demande de pack groupe (`create`), suit ses demandes
 * (`mine`), consulte le détail avec les devis composés par le back-office
 * (`get`) et accepte un devis qui lui a été envoyé (`acceptQuote`). Tous les
 * endpoints exigent une session (le `tokenInterceptor` pose le Bearer) ;
 * l'autorisation fine est portée par les policies backend (403 = pas ma
 * demande, 422 = devis non « envoyé » donc non acceptable).
 */
@Injectable({ providedIn: 'root' })
export class TeamBuildingService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** POST /team-building-requests — dépose une demande de pack groupe. */
  create(
    payload: CreateTeamBuildingRequestPayload,
  ): Observable<ApiEnvelope<{ request: TeamBuildingRequest }>> {
    return this.http.post<ApiEnvelope<{ request: TeamBuildingRequest }>>(
      `${this.api}/team-building-requests`,
      this.clean(payload),
    );
  }

  /** GET /team-building-requests/mine — mes demandes (paginé 15, récentes d'abord). */
  myRequests(page = 1): Observable<Paginated<TeamBuildingRequest>> {
    return this.http.get<Paginated<TeamBuildingRequest>>(
      `${this.api}/team-building-requests/mine`,
      { params: { page: String(page) } },
    );
  }

  /** GET /team-building-requests/{id} — détail d'UNE de mes demandes (avec devis). */
  get(id: number | string): Observable<ApiEnvelope<{ request: TeamBuildingRequest }>> {
    return this.http.get<ApiEnvelope<{ request: TeamBuildingRequest }>>(
      `${this.api}/team-building-requests/${id}`,
    );
  }

  /**
   * PATCH /team-building-quotes/{id}/accept — accepte un devis « envoyé ».
   * Le backend refuse (422) un devis qui n'est pas au statut `envoye` : l'écran
   * ne propose donc le bouton que dans ce cas.
   */
  acceptQuote(quoteId: number | string): Observable<ApiEnvelope<{ quote: TeamBuildingQuote }>> {
    return this.http.patch<ApiEnvelope<{ quote: TeamBuildingQuote }>>(
      `${this.api}/team-building-quotes/${quoteId}/accept`,
      {},
    );
  }

  /**
   * Retire les clés optionnelles vides pour ne transmettre que le renseigné.
   * (`needs` est conservé même vide si l'appelant le fournit explicitement.)
   */
  private clean(
    payload: CreateTeamBuildingRequestPayload,
  ): Record<string, unknown> {
    const body: Record<string, unknown> = {};
    for (const [key, value] of Object.entries(payload)) {
      if (value === null || value === undefined || value === '') {
        continue;
      }
      body[key] = value;
    }
    return body;
  }
}
