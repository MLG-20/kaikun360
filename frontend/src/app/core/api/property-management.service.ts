import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { Property } from '../../models/property.model';
import { ApiEnvelope } from './api-response.model';

/**
 * Nature d'un bien — miroir de l'enum `PropertyType` backend
 * (colonne `properties.type`). Alimente le sélecteur de type du formulaire.
 */
export type PropertyType =
  | 'appartement'
  | 'maison'
  | 'villa'
  | 'studio'
  | 'terrain'
  | 'bureau'
  | 'commerce'
  | 'autre';

/**
 * Corps de `POST /properties` — miroir EXACT de `StorePropertyRequest`.
 *
 * `type`, `title`, `region_id` et `department_id` sont requis ; le département
 * doit appartenir à la région et la commune au département (cohérence vérifiée
 * côté serveur → les sélecteurs en cascade garantissent des valeurs valides).
 */
export interface CreatePropertyPayload {
  type: PropertyType;
  title: string;
  region_id: number;
  department_id: number;
  description?: string | null;
  price_xof?: number | null;
  commune_id?: number | null;
  tourist_zone?: string | null;
  address?: string | null;
  latitude?: number | null;
  longitude?: number | null;
}

/**
 * Dépôt de bien par un propriétaire (F2.7).
 *
 * `POST /properties` exige une session ET un **compte vérifié** (middleware
 * `verified.account` → 403 sinon) ; la page gère ce cas en amont. Le bien créé
 * part en file de validation (statut `en_attente_validation`) : il n'est pas
 * publié tant qu'un agent ne l'a pas approuvé (B4). Les photos/documents sont
 * ajoutés séparément (endpoints médias) — hors périmètre de ce formulaire.
 */
@Injectable({ providedIn: 'root' })
export class PropertyManagementService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** POST /properties — dépose un bien (auth + vérifié). */
  create(payload: CreatePropertyPayload): Observable<ApiEnvelope<{ property: Property }>> {
    return this.http.post<ApiEnvelope<{ property: Property }>>(
      `${this.api}/properties`,
      this.clean(payload),
    );
  }

  /** Retire les clés optionnelles vides (null/undefined/chaîne vide). */
  private clean(payload: CreatePropertyPayload): Record<string, unknown> {
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
