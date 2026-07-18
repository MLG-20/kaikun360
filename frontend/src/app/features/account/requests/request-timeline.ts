import { ServiceRequest } from '../../../models/service-request.model';

/**
 * Une étape de la chronologie d'une demande (miroir de l'enum `RequestStatus`
 * backend, machine à états STRICTE : recu → verification → visite → devis →
 * negociation → cloture). L'ordre de ce tableau EST l'ordre des étapes.
 */
export interface RequestStep {
  value: string;
  label: string;
}

/** Étapes de la chronologie, dans l'ordre de la machine à états stricte. */
export const REQUEST_STEPS: readonly RequestStep[] = [
  { value: 'recu', label: 'Reçu' },
  { value: 'verification', label: 'Vérification' },
  { value: 'visite', label: 'Visite' },
  { value: 'devis', label: 'Devis' },
  { value: 'negociation', label: 'Négociation' },
  { value: 'cloture', label: 'Clôturé' },
];

/**
 * Indice de l'étape correspondant à un statut dans `REQUEST_STEPS`
 * (−1 si statut inconnu). Sert à teinter la chronologie.
 */
export function stepIndex(status: string | null): number {
  return REQUEST_STEPS.findIndex((s) => s.value === status);
}

/** État d'une étape (par son indice) par rapport au statut courant d'une demande. */
export function stepState(req: ServiceRequest, i: number): 'done' | 'current' | 'todo' {
  const current = stepIndex(req.status);
  if (i < current) {
    return 'done';
  }
  if (i === current) {
    return 'current';
  }
  return 'todo';
}
