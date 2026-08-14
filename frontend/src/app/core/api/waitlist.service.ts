import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';

/**
 * 5 catégories d'inscrits à la liste d'attente — miroir de `WaitlistCategory`
 * côté serveur.
 */
export type WaitlistCategory =
  | 'proprietaire'
  | 'prestataire'
  | 'client'
  | 'team_building'
  | 'diaspora';

/**
 * Champs spécifiques à chaque catégorie, stockés dans `details` (JSON) côté
 * serveur — voir `StoreWaitlistEntryRequest`.
 */
export interface WaitlistDetails {
  // Propriétaire
  type_bien?: 'villa' | 'appartement' | 'terrain' | 'commerce' | 'autre';
  nb_biens?: number;
  // Prestataire
  type_service?: 'mobilite' | 'tourisme' | 'btp' | 'team_building' | 'autre';
  // Client intéressé
  univers?: 'immobilier' | 'sejours' | 'transport' | 'services';
  // Team building
  taille_equipe?: number;
  budget_xof?: number;
  // Diaspora
  pays_residence?: string;
  type_projet?: 'achat' | 'gestion_locative' | 'construction';
}

/**
 * Corps de `POST /waitlist` — miroir de `StoreWaitlistEntryRequest`.
 */
export interface WaitlistEntryPayload {
  name: string;
  phone: string;
  email?: string | null;
  city?: string | null;
  category: WaitlistCategory;
  details: WaitlistDetails;
  precisions?: string | null;
}

/**
 * Inscription à la liste d'attente avant ouverture officielle (2026-08-14).
 *
 * L'endpoint `POST /waitlist` est **public** (un prospect sans compte doit
 * pouvoir s'inscrire) et limité en débit côté backend. Détaché de la page
 * statique que le client maintient sur `kaikun360.com` — voir le README.
 */
@Injectable({ providedIn: 'root' })
export class WaitlistService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** POST /waitlist — enregistre une inscription (public). */
  create(payload: WaitlistEntryPayload): Observable<unknown> {
    const body: Record<string, unknown> = {
      name: payload.name,
      phone: payload.phone,
      category: payload.category,
      details: payload.details,
    };
    if (payload.email) {
      body['email'] = payload.email;
    }
    if (payload.city) {
      body['city'] = payload.city;
    }
    if (payload.precisions) {
      body['precisions'] = payload.precisions;
    }
    return this.http.post(`${this.api}/waitlist`, body);
  }
}
