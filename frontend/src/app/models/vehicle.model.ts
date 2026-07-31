/**
 * Véhicule — miroir de `VehicleResource` (module Mobility).
 */
export interface Vehicle {
  id: number;
  reference: string;
  type: string | null;
  type_label: string | null;
  brand: string | null;
  model: string | null;
  capacity: number;
  price_per_day_xof: number;
  has_driver: boolean;
  caution_xof: number | null;
  description: string | null;
  status: string | null;
  status_label: string | null;
  published_at: string | null;
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
 * Véhicule vu depuis le **back-office** — miroir d'`AdminVehicleResource`
 * (F7.2.j).
 *
 * Sur-ensemble de `Vehicle` : ajoute les champs de **contrôle de conformité**
 * que le catalogue public ne montre pas (assurance, identité du chauffeur,
 * gilets de sauvetage des pirogues, drapeaux météo/prestataire) et le
 * prestataire propriétaire, pour que l'agent puisse le joindre en cas
 * d'anomalie. Servi uniquement par `GET /admin/vehicles`.
 */
export interface AdminVehicle extends Vehicle {
  /** Référence du contrat d'assurance (null = manquante → non conforme). */
  insurance_ref: string | null;
  /** Identité du chauffeur déclaré (null = non renseignée). */
  driver_identity: string | null;
  /** Gilets de sauvetage à bord (pirogues ; null hors transport fluvial). */
  life_jackets_count: number | null;
  /** Conformité météo (pirogues). */
  weather_compliant: boolean | null;
  /** Conformité du prestataire (pirogues). */
  provider_compliant: boolean | null;
  /** Le prestataire propriétaire (absent si non chargé). */
  provider?: { id: number; name: string; email: string | null; phone: string | null } | null;
  created_at: string | null;
}
