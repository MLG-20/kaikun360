/**
 * Simulateur de budget de construction — miroir FIDÈLE du calcul backend
 * `App\Modules\Build\Services\ConstructionEstimator` (phase B5.4).
 *
 * Pourquoi dupliquer côté client ? L'endpoint `POST /construction-requests/simulate`
 * exige une session authentifiée, alors que la page Construction est **publique**
 * (page de conversion). Le calcul étant purement déterministe et explicitement
 * INDICATIF (le devis ferme relève de la couche Quotes), on le rejoue en local
 * pour offrir une estimation immédiate sans obliger le visiteur à se connecter.
 *
 * ⚠️ Les constantes ci-dessous DOIVENT rester alignées sur le backend. Si les
 * tarifs de référence changent côté serveur, les répercuter ici.
 */

/** Objectif des travaux — miroir de l'enum `ConstructionObjective`. */
export type ConstructionObjective = 'construction_neuve' | 'renovation' | 'extension';

/** Niveau de finition — miroir de l'enum `FinishLevel`. */
export type FinishLevel = 'economique' | 'standard' | 'premium';

/** Coût de base au m² (XOF) selon l'objectif (identique au backend). */
const BASE_PRICE_PER_M2: Record<ConstructionObjective, number> = {
  construction_neuve: 250_000,
  extension: 220_000,
  renovation: 150_000,
};

/** Coefficient multiplicateur selon le niveau de finition (identique au backend). */
const FINISH_COEFFICIENT: Record<FinishLevel, number> = {
  economique: 0.85,
  standard: 1.0,
  premium: 1.35,
};

/** Pas d'arrondi de l'estimation (pour rester indicatif). */
const ROUNDING_STEP = 100_000;

/** Détail structuré de l'estimation (même forme que le `breakdown` backend). */
export interface ConstructionEstimate {
  objective: ConstructionObjective;
  surface_m2: number;
  finish_level: FinishLevel;
  price_per_m2_xof: number;
  finish_coefficient: number;
  estimated_cost_xof: number;
}

/** Libellés lisibles des objectifs (pour l'IHM). */
export const OBJECTIVE_LABELS: Record<ConstructionObjective, string> = {
  construction_neuve: 'Construction neuve',
  renovation: 'Rénovation',
  extension: 'Extension',
};

/** Libellés lisibles des niveaux de finition (pour l'IHM). */
export const FINISH_LABELS: Record<FinishLevel, string> = {
  economique: 'Économique',
  standard: 'Standard',
  premium: 'Premium',
};

/**
 * Estimation indicative du coût total (XOF), arrondie au pas le plus proche —
 * reproduit exactement `ConstructionEstimator::estimate`.
 */
export function estimateConstructionCost(
  objective: ConstructionObjective,
  surfaceM2: number,
  finishLevel: FinishLevel,
): number {
  const pricePerM2 = BASE_PRICE_PER_M2[objective];
  const coefficient = FINISH_COEFFICIENT[finishLevel];
  const raw = pricePerM2 * Math.max(0, surfaceM2) * coefficient;

  return Math.round(raw / ROUNDING_STEP) * ROUNDING_STEP;
}

/** Détail complet de l'estimation — reproduit `ConstructionEstimator::breakdown`. */
export function breakdownConstructionCost(
  objective: ConstructionObjective,
  surfaceM2: number,
  finishLevel: FinishLevel,
): ConstructionEstimate {
  return {
    objective,
    surface_m2: surfaceM2,
    finish_level: finishLevel,
    price_per_m2_xof: BASE_PRICE_PER_M2[objective],
    finish_coefficient: FINISH_COEFFICIENT[finishLevel],
    estimated_cost_xof: estimateConstructionCost(objective, surfaceM2, finishLevel),
  };
}
