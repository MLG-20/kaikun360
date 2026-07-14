/**
 * Expérience touristique — miroir de `ExperienceResource` (module Explore).
 * `seats_left` n'est présent que sur l'endpoint de disponibilité.
 */
export interface Experience {
  id: number;
  reference: string;
  title: string;
  destination: string;
  description: string | null;
  duration_days: number;
  price_xof: number;
  capacity: number;
  /**
   * Inclusions structurées : clé d'inclusion → incluse ou non
   * (ex. `{ restauration: true, guide: true, transport: false }`). Le backend
   * renvoie `[]` (tableau vide) lorsqu'aucune inclusion n'est renseignée.
   */
  inclusions: Record<string, boolean> | never[];
  status: string | null;
  status_label: string | null;
  published_at: string | null;
  seats_left?: number;
}

/**
 * Disponibilité d'une expérience — miroir de `GET /experiences/{id}/availability`.
 * Alimente l'affichage des places restantes sur la fiche (F2.4).
 */
export interface ExperienceAvailability {
  experience_id: number;
  capacity: number;
  seats_left: number;
}
