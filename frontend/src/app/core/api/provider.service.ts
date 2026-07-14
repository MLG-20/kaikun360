import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { Provider } from '../../models/provider.model';
import { ApiEnvelope } from './api-response.model';

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
