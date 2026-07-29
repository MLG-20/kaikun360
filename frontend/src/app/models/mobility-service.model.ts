/**
 * Service de mobilité (navette, transfert, liaison, excursion) —
 * miroir de `MobilityServiceResource` (module Mobility).
 */
export interface MobilityService {
  id: number;
  reference: string;
  type: string | null;
  type_label: string | null;
  departure: string;
  destination: string;
  departure_at: string | null;
  capacity: number;
  price_xof: number;
  description: string | null;
  status: string | null;
  status_label: string | null;
}

/**
 * Trajet vu depuis le **back-office** — miroir d'`AdminMobilityServiceResource`
 * (F7.2.j).
 *
 * Sur-ensemble de `MobilityService` : ajoute le **remplissage** du départ (les
 * « disponibilités » du cahier des charges), le véhicule affecté et le
 * prestataire opérateur. Servi uniquement par `GET /admin/mobility-services`.
 */
export interface AdminMobilityService extends MobilityService {
  /** Places déjà réservées (réservations annulées exclues). */
  seats_taken: number;
  /** Places restantes = capacité − places prises (jamais négatif). */
  seats_left: number;
  /** Véhicule rattaché (un trajet peut être annoncé sans véhicule nommé). */
  vehicle?: {
    id: number;
    reference: string;
    label: string | null;
    type_label: string | null;
    capacity: number;
  } | null;
  provider?: { id: number; name: string; email: string | null; phone: string | null } | null;
  created_at: string | null;
}
