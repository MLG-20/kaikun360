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
