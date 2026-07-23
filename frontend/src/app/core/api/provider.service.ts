import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import {
  MissionAction,
  NewUnavailability,
  Provider,
  ProviderAvailability,
  ProviderEarnings,
  ProviderMission,
  Unavailability,
  WeeklyAvailability,
} from '../../models/provider.model';
import { ApiEnvelope } from './api-response.model';
import { Paginated } from './pagination.model';

/**
 * Catégorie de prestataire — miroir de l'enum `ProviderCategory` backend
 * (colonne `providers.category`). Sert à alimenter le sélecteur du formulaire.
 */
export type ProviderCategory =
  | 'restauration'
  | 'animation'
  | 'guide'
  | 'transport'
  | 'evenementiel'
  | 'artisanat'
  | 'autre';

/** Une certification proposée à l'inscription (nom + organisme facultatif). */
export interface ProviderCertificationInput {
  name: string;
  issuer?: string | null;
}

/**
 * Corps de `POST /providers` — miroir EXACT de `RegisterProviderRequest`.
 * `business_name` et `category` sont requis ; `bio` et `certifications` sont
 * facultatifs.
 */
export interface RegisterProviderPayload {
  business_name: string;
  category: ProviderCategory;
  bio?: string | null;
  certifications?: ProviderCertificationInput[];
}

/**
 * Inscription et suivi du profil prestataire (F2.7).
 *
 * `register` (POST /providers) exige une session ET un **compte vérifié**
 * (middleware `verified.account` → 403 sinon) ; la page gère ce cas en amont.
 * `mine` (GET /providers/mine) renvoie **404** tant qu'aucun profil n'existe :
 * le front s'en sert pour distinguer « pas encore inscrit » (→ formulaire) de
 * « déjà inscrit » (→ affichage du statut de validation).
 */
@Injectable({ providedIn: 'root' })
export class ProviderService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** POST /providers — crée mon profil prestataire (auth + vérifié). */
  register(
    payload: RegisterProviderPayload,
  ): Observable<ApiEnvelope<{ provider: Provider }>> {
    return this.http.post<ApiEnvelope<{ provider: Provider }>>(
      `${this.api}/providers`,
      this.clean(payload),
    );
  }

  /** GET /providers/mine — mon profil prestataire (404 si aucun). */
  mine(): Observable<ApiEnvelope<{ provider: Provider }>> {
    return this.http.get<ApiEnvelope<{ provider: Provider }>>(`${this.api}/providers/mine`);
  }

  /**
   * GET /provider-missions/mine — mes missions (F5.2).
   *
   * Liste paginée (15/page, plus récentes d'abord), forme standard Laravel
   * `{ data, links, meta }`. Scopée côté serveur au prestataire connecté.
   */
  myMissions(page = 1): Observable<Paginated<ProviderMission>> {
    return this.http.get<Paginated<ProviderMission>>(`${this.api}/provider-missions/mine`, {
      params: { page: String(page) },
    });
  }

  /**
   * GET /provider-missions/earnings — synthèse revenus & commissions (F5.3).
   *
   * Agrégat scopé au prestataire connecté : réalisé (missions terminées), à venir
   * (acceptées + en cours) et nombre de missions à traiter.
   */
  earnings(): Observable<ApiEnvelope<ProviderEarnings>> {
    return this.http.get<ApiEnvelope<ProviderEarnings>>(
      `${this.api}/provider-missions/earnings`,
    );
  }

  /**
   * GET /providers/availability — mes disponibilités (F5.4) : planning
   * hebdomadaire (7 jours) + périodes d'indisponibilité à venir.
   */
  availability(): Observable<ApiEnvelope<ProviderAvailability>> {
    return this.http.get<ApiEnvelope<ProviderAvailability>>(
      `${this.api}/providers/availability`,
    );
  }

  /**
   * PUT /providers/availability/weekly — enregistre le planning hebdomadaire.
   * Renvoie le planning à jour (les 7 jours).
   */
  saveWeekly(
    days: WeeklyAvailability[],
  ): Observable<ApiEnvelope<{ weekly: WeeklyAvailability[] }>> {
    return this.http.put<ApiEnvelope<{ weekly: WeeklyAvailability[] }>>(
      `${this.api}/providers/availability/weekly`,
      { days },
    );
  }

  /** POST /providers/availability/unavailability — ajoute une indisponibilité. */
  addUnavailability(
    payload: NewUnavailability,
  ): Observable<ApiEnvelope<{ unavailability: Unavailability }>> {
    return this.http.post<ApiEnvelope<{ unavailability: Unavailability }>>(
      `${this.api}/providers/availability/unavailability`,
      payload,
    );
  }

  /** DELETE /providers/availability/unavailability/{id} — supprime une indisponibilité. */
  removeUnavailability(id: number): Observable<ApiEnvelope<{ deleted: boolean }>> {
    return this.http.delete<ApiEnvelope<{ deleted: boolean }>>(
      `${this.api}/providers/availability/unavailability/${id}`,
    );
  }

  /**
   * PATCH /provider-missions/{id}/{action} — fait progresser une mission (F5.2).
   *
   * `action` ∈ `accept` | `refuse` | `start` | `complete`. Le backend valide la
   * transition depuis le statut courant (422 si impossible) et n'autorise que le
   * prestataire affecté (403 sinon). Renvoie la mission mise à jour.
   */
  transitionMission(
    id: number,
    action: MissionAction,
  ): Observable<ApiEnvelope<{ mission: ProviderMission }>> {
    return this.http.patch<ApiEnvelope<{ mission: ProviderMission }>>(
      `${this.api}/provider-missions/${id}/${action}`,
      {},
    );
  }

  /**
   * Prépare le corps : retire la bio vide et les certifications non nommées
   * (le backend rejette une certification sans nom via `required_with`).
   */
  private clean(payload: RegisterProviderPayload): Record<string, unknown> {
    const body: Record<string, unknown> = {
      business_name: payload.business_name,
      category: payload.category,
    };
    if (payload.bio) {
      body['bio'] = payload.bio;
    }
    const certifications = (payload.certifications ?? [])
      .filter((c) => c.name.trim() !== '')
      .map((c) => ({
        name: c.name.trim(),
        ...(c.issuer && c.issuer.trim() !== '' ? { issuer: c.issuer.trim() } : {}),
      }));
    if (certifications.length) {
      body['certifications'] = certifications;
    }
    return body;
  }
}
