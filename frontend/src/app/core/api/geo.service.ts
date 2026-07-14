import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { ApiEnvelope } from './api-response.model';

/** Région du Sénégal (référentiel géographique). */
export interface Region {
  id: number;
  name: string;
}

/** Département, rattaché à une région. */
export interface Department {
  id: number;
  region_id: number;
  name: string;
}

/** Commune, rattachée à un département. */
export interface Commune {
  id: number;
  department_id: number;
  name: string;
  type: string | null;
}

/**
 * Référentiel géographique en lecture (F2.7).
 *
 * Alimente les sélecteurs EN CASCADE du dépôt de bien : on charge d'abord les
 * régions, puis — au choix d'une région — ses départements, puis — au choix
 * d'un département — ses communes. Miroir des endpoints publics `GeoController`
 * (F2.7.0) : les listes enfants exigent l'identifiant du parent.
 */
@Injectable({ providedIn: 'root' })
export class GeoService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** GET /regions — toutes les régions. */
  regions(): Observable<ApiEnvelope<Region[]>> {
    return this.http.get<ApiEnvelope<Region[]>>(`${this.api}/regions`);
  }

  /** GET /departments?region_id= — départements d'une région. */
  departments(regionId: number): Observable<ApiEnvelope<Department[]>> {
    return this.http.get<ApiEnvelope<Department[]>>(`${this.api}/departments`, {
      params: { region_id: regionId },
    });
  }

  /** GET /communes?department_id= — communes d'un département. */
  communes(departmentId: number): Observable<ApiEnvelope<Commune[]>> {
    return this.http.get<ApiEnvelope<Commune[]>>(`${this.api}/communes`, {
      params: { department_id: departmentId },
    });
  }
}
