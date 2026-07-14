import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { ServiceRequest } from '../../models/service-request.model';
import { ApiEnvelope } from './api-response.model';

/**
 * Univers visé par une demande générique — miroir de l'enum `ServiceType`
 * backend (colonne `requests.service_type`). Toute demande contextuelle du
 * frontend (visite d'un bien, réservation d'une nuitée…) s'y rattache.
 */
export type ServiceType =
  | 'immo'
  | 'stay'
  | 'manage'
  | 'build'
  | 'explore'
  | 'mobility'
  | 'diaspora'
  | 'team_building'
  | 'pro'
  | 'autre';

/** Priorité optionnelle d'une demande (miroir de l'enum `RequestPriority`). */
export type RequestPriority = 'basse' | 'normale' | 'haute' | 'urgente';

/**
 * Corps de `POST /requests` — miroir EXACT de `StoreRequestRequest`.
 * Seuls `service_type` et `message` sont requis ; les clés inconnues seraient
 * ignorées côté serveur.
 */
export interface CreateRequestPayload {
  service_type: ServiceType;
  message: string;
  budget_xof?: number | null;
  city?: string | null;
  priority?: RequestPriority | null;
}

/**
 * Accès aux demandes de service (F2.3).
 *
 * Porte les formulaires contextuels des pages publiques : « demander une
 * visite » sur une fiche immobilier, « demander une réservation » sur une fiche
 * nuitée, etc. L'endpoint exige une session authentifiée (le `tokenInterceptor`
 * ajoute le Bearer) ; un appel anonyme reçoit un 401 détourné par
 * l'`errorInterceptor` vers la page de connexion. Les pages appelantes gardent
 * donc l'accès derrière `AuthService.isAuthenticated`.
 */
@Injectable({ providedIn: 'root' })
export class RequestService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** POST /requests — dépose une demande contextuelle (auth requise). */
  create(payload: CreateRequestPayload): Observable<ApiEnvelope<{ request: ServiceRequest }>> {
    const body = this.clean(payload);
    return this.http.post<ApiEnvelope<{ request: ServiceRequest }>>(
      `${this.api}/requests`,
      body,
    );
  }

  /**
   * Retire les clés optionnelles vides (null/undefined/chaîne vide) pour ne
   * transmettre que ce que l'utilisateur a réellement renseigné.
   */
  private clean(payload: CreateRequestPayload): Record<string, unknown> {
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
