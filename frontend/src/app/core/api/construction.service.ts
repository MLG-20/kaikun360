import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { ApiEnvelope } from './api-response.model';

/**
 * Simulateur de budget de construction (F2.5, enrichi).
 *
 * Le calcul vit ENTIÈREMENT côté backend (`ConstructionEstimator`, source unique
 * dont le barème est géré au back-office via le réglage `build.pricing`). Le
 * frontend ne fait que collecter les paramètres et afficher le détail renvoyé —
 * plus aucune duplication de tarifs côté client.
 *
 * L'endpoint `POST /construction-requests/simulate` est **public** : la page
 * Construction est accessible sans compte.
 */

/** Objectif des travaux — miroir de l'enum backend `ConstructionObjective`. */
export type ConstructionObjective = 'construction_neuve' | 'renovation' | 'extension';

/** Niveau de finition — miroir de l'enum backend `FinishLevel`. */
export type FinishLevel = 'economique' | 'standard' | 'premium';

/** Zone géographique — miroir de l'enum backend `ConstructionZone`. */
export type ConstructionZone = 'dakar' | 'autres_regions' | 'zones_eloignees';

/** Mode d'exploitation locative renvoyé dans la projection de rentabilité. */
export type RentalMode = 'longue_duree' | 'nuitee';

/** Libellés lisibles (IHM). */
export const OBJECTIVE_LABELS: Record<ConstructionObjective, string> = {
  construction_neuve: 'Construction neuve',
  renovation: 'Rénovation',
  extension: 'Extension',
};

export const FINISH_LABELS: Record<FinishLevel, string> = {
  economique: 'Économique',
  standard: 'Standard',
  premium: 'Premium',
};

export const ZONE_LABELS: Record<ConstructionZone, string> = {
  dakar: 'Dakar & Thiès',
  autres_regions: 'Autres régions',
  zones_eloignees: 'Zones éloignées (Casamance, Sénégal oriental)',
};

export const RENTAL_MODE_LABELS: Record<RentalMode, string> = {
  longue_duree: 'Location longue durée',
  nuitee: 'Location à la nuitée',
};

/** Une région du Sénégal, rattachée à une zone de coût du simulateur. */
export interface RegionOption {
  name: string;
  zone: ConstructionZone;
}

/**
 * Les 14 régions du Sénégal, chacune rattachée à une zone de coût.
 *
 * Ce classement (regroupement géographique → 3 zones) est un confort d'IHM : il
 * évite à l'utilisateur de raisonner en « zones » abstraites, il choisit sa
 * région et le simulateur en déduit le coefficient. ⚠️ Les COEFFICIENTS de zone
 * eux-mêmes restent gérés côté backend (`build.pricing.zone_coeff`, back-office).
 * Zones éloignées = Casamance (Ziguinchor, Sédhiou, Kolda) + Sénégal oriental
 * (Tambacounda, Kédougou) + Matam (transport matériaux le plus long).
 */
export const SENEGAL_REGIONS: RegionOption[] = [
  { name: 'Dakar', zone: 'dakar' },
  { name: 'Thiès', zone: 'dakar' },
  { name: 'Diourbel', zone: 'autres_regions' },
  { name: 'Fatick', zone: 'autres_regions' },
  { name: 'Kaffrine', zone: 'autres_regions' },
  { name: 'Kaolack', zone: 'autres_regions' },
  { name: 'Louga', zone: 'autres_regions' },
  { name: 'Saint-Louis', zone: 'autres_regions' },
  { name: 'Kédougou', zone: 'zones_eloignees' },
  { name: 'Kolda', zone: 'zones_eloignees' },
  { name: 'Matam', zone: 'zones_eloignees' },
  { name: 'Sédhiou', zone: 'zones_eloignees' },
  { name: 'Tambacounda', zone: 'zones_eloignees' },
  { name: 'Ziguinchor', zone: 'zones_eloignees' },
];

/** Corps de `POST /construction-requests/simulate` — miroir de `SimulateConstructionRequest`. */
export interface SimulatePayload {
  objective: ConstructionObjective;
  surface_m2: number;
  finish_level: FinishLevel;
  levels?: number;
  zone?: ConstructionZone;
  land_cost_xof?: number;
}

/** Une part chiffrée (poste de coût ou jalon de paiement). */
export interface CostShare {
  key: string;
  label: string;
  ratio: number;
  amount_xof: number;
}

/** Projection de rentabilité pour un mode d'exploitation. */
export interface RentalProjection {
  monthly_income_xof: number;
  yield_min_pct: number;
  yield_max_pct: number;
  payback_years: number;
}

/** Détail complet renvoyé par le simulateur — miroir de `ConstructionEstimator::breakdown`. */
export interface Simulation {
  estimated_cost_xof: number;
  inputs: {
    objective: ConstructionObjective;
    surface_m2: number;
    levels: number;
    total_surface_m2: number;
    finish_level: FinishLevel;
    zone: ConstructionZone;
    land_cost_xof: number;
  };
  works: {
    price_per_m2_xof: number;
    finish_coefficient: number;
    zone_coefficient: number;
    cost_xof: number;
    breakdown: CostShare[];
    milestones: CostShare[];
  };
  fees: {
    items: CostShare[];
    total_xof: number;
  };
  land: {
    included: boolean;
    cost_xof: number;
    acquisition_rate: number;
    acquisition_fees_xof: number;
    total_xof: number;
  };
  grand_total_xof: number;
  duration: { min_months: number; max_months: number };
  rental: Record<RentalMode, RentalProjection>;
}

@Injectable({ providedIn: 'root' })
export class ConstructionService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** POST /construction-requests/simulate — chiffrage complet (public). */
  simulate(payload: SimulatePayload): Observable<ApiEnvelope<{ simulation: Simulation }>> {
    return this.http.post<ApiEnvelope<{ simulation: Simulation }>>(
      `${this.api}/construction-requests/simulate`,
      payload,
    );
  }
}
