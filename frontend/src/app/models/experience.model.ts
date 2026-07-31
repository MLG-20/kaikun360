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
  /**
   * Compteurs de médias (F8.1), fournis par les listes back-office uniquement.
   *
   * Servent à repérer dans le catalogue de supervision une annonce publiée
   * SANS visuel, ou dont des photos ont été masquées par la modération.
   */
  media_count?: number;
  media_hidden_count?: number;
}

/**
 * Circuit vu depuis le **back-office** — miroir d'`AdminExperienceResource`
 * (F7.2.k).
 *
 * Sur-ensemble d'`Experience` : ajoute le **remplissage** du circuit (les
 * « capacités groupes » du cahier des charges) et le prestataire opérateur.
 * Servi uniquement par `GET /admin/experiences`.
 *
 * ⚠️ Un circuit n'a **pas de date de départ** : sa capacité est un total, et
 * `seats_taken` cumule toutes ses réservations non annulées — ce n'est pas le
 * remplissage d'une session datée (contrairement à `AdminMobilityService`).
 */
export interface AdminExperience extends Experience {
  seats_taken: number;
  seats_left: number;
  provider?: { id: number; name: string; email: string | null; phone: string | null } | null;
  created_at: string | null;
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
