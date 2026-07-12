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
  inclusions: string[];
  status: string | null;
  status_label: string | null;
  published_at: string | null;
  seats_left?: number;
}
