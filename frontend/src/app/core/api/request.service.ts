import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { ServiceRequest } from '../../models/service-request.model';
import { ApiEnvelope } from './api-response.model';
import { Paginated } from './pagination.model';

/**
 * Univers visé par une demande générique — miroir de l'enum `ServiceType`
 * backend (colonne `requests.service_type`). Toute demande contextuelle du
 * frontend (visite d'un bien, réservation d'une nuitée…) s'y rattache.
 */
export type ServiceType =
  | 'immo'
  | 'stay'
  | 'manage'
  /**
   * ⚠️ **HISTORIQUE — ne plus l'envoyer.** Depuis F8.15.b, le serveur REFUSE une
   * demande générique de type `build` : un chantier se dépose sur
   * `POST /construction-requests` (cf. `ConstructionService.create`), qui ouvre
   * un vrai dossier avec estimation, jalons, rapports et devis par lot. Le type
   * reste ici parce que `GET /requests/my` peut renvoyer d'anciennes demandes
   * qui le portent : le retirer ferait mentir la lecture.
   */
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
   * GET /requests/my — les demandes de l'utilisateur connecté (F3.3).
   *
   * Renvoie une liste **paginée** (15 par page, plus récentes d'abord) sous la
   * forme standard Laravel `{ data, links, meta }`. Alimente l'écran « Mes
   * demandes » de l'espace client. Auth requise (Bearer posé par
   * l'intercepteur ; un appel anonyme est détourné vers la connexion).
   */
  myRequests(page = 1): Observable<Paginated<ServiceRequest>> {
    return this.http.get<Paginated<ServiceRequest>>(`${this.api}/requests/my`, {
      params: { page: String(page) },
    });
  }

  /**
   * GET /requests/{id} — détail d'UNE de mes demandes (F3.3).
   *
   * Réservé au propriétaire de la demande (403 sinon, détourné par
   * l'`errorInterceptor`). Alimente l'écran de détail atteint en cliquant une
   * carte depuis « Mes demandes ». Auth requise (Bearer posé par l'intercepteur).
   */
  get(id: number | string): Observable<ApiEnvelope<{ request: ServiceRequest }>> {
    return this.http.get<ApiEnvelope<{ request: ServiceRequest }>>(
      `${this.api}/requests/${id}`,
    );
  }

  /**
   * POST /requests/{id}/hide — range une demande dans la corbeille (F11.5).
   *
   * ⚠️ **Ne supprime rien** : la demande quitte la liste du client et de nulle
   * part ailleurs — elle reste la pièce d'un dossier que Kaikun continue de
   * voir. Le serveur refuse (422) tout ce qui n'est pas CLÔTURÉ ; c'est ce que
   * dit déjà le drapeau `hideable` de la ressource.
   */
  hide(id: number): Observable<ApiEnvelope<{ message: string }>> {
    return this.http.post<ApiEnvelope<{ message: string }>>(
      `${this.api}/requests/${id}/hide`,
      {},
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
