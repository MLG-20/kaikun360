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
}
