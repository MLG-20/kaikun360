import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { Mandate, MonthlyReport, OwnerDashboard } from '../../models/manage.model';
import { ApiEnvelope } from './api-response.model';
import { Paginated } from './pagination.model';

/**
 * Accès à la **gestion locative** du propriétaire connecté (espace propriétaire,
 * F4). Côté backend, ces endpoints (module Manage) sont scopés au propriétaire :
 * on ne voit QUE les données de ses propres biens. Auth requise (Bearer posé par
 * l'intercepteur ; un appel anonyme est détourné vers la connexion).
 */
@Injectable({ providedIn: 'root' })
export class ManageService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /**
   * GET /manage/dashboard — agrégats de gestion locative du propriétaire (F4.1) :
   * mandats actifs, loyers payés/impayés, dépenses, reversements, incidents.
   */
  dashboard(): Observable<ApiEnvelope<OwnerDashboard>> {
    return this.http.get<ApiEnvelope<OwnerDashboard>>(`${this.api}/manage/dashboard`);
  }

  /**
   * GET /manage/mandates/mine — mes mandats de gestion locative (F4.4).
   *
   * Liste paginée (15/page, plus récents d'abord), forme standard Laravel
   * `{ data, links, meta }`. Chaque mandat porte son bien et ses agrégats
   * financiers (`summary`) mais PAS ses lignes détaillées (fiche seulement).
   */
  myMandates(page = 1): Observable<Paginated<Mandate>> {
    return this.http.get<Paginated<Mandate>>(`${this.api}/manage/mandates/mine`, {
      params: { page: String(page) },
    });
  }

  /**
   * GET /manage/mandates/{id} — fiche d'un mandat (F4.4).
   *
   * Réservé au propriétaire du mandat (ou agent/admin) — 403 sinon. Embarque le
   * bien, les agrégats et les LIGNES détaillées (loyers, reversements, incidents,
   * les 12 plus récents de chaque).
   */
  getMandate(id: number | string): Observable<ApiEnvelope<{ mandate: Mandate }>> {
    return this.http.get<ApiEnvelope<{ mandate: Mandate }>>(`${this.api}/manage/mandates/${id}`);
  }

  /**
   * GET /manage/mandates/{id}/report — rapport mensuel d'un mandat (F4.4).
   *
   * `month` au format `YYYY-MM` (mois courant par défaut). Renvoie des données
   * agrégées bornées au mois : loyers encaissés/impayés, dépenses, commission
   * Kaikun, net dû au propriétaire, reversements et incidents.
   */
  mandateReport(
    id: number | string,
    month?: string,
  ): Observable<ApiEnvelope<{ report: MonthlyReport }>> {
    return this.http.get<ApiEnvelope<{ report: MonthlyReport }>>(
      `${this.api}/manage/mandates/${id}/report`,
      month ? { params: { month } } : {},
    );
  }
}
