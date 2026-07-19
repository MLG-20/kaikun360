import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { OwnerDashboard } from '../../models/manage.model';
import { ApiEnvelope } from './api-response.model';

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
}
