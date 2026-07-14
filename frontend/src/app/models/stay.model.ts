import { Property } from './property.model';

/**
 * Nuitée — miroir de `StayResource` (module Stay). Embarque le bien associé
 * lorsqu'il est chargé côté API.
 */
export interface Stay {
  id: number;
  price_per_night_xof: number;
  caution_xof: number | null;
  capacity: number;
  min_nights: number | null;
  max_nights: number | null;
  rules: string[] | null;
  amenities: string[] | null;
  check_in_time: string | null;
  check_out_time: string | null;
  property?: Property;
  created_at: string | null;
}

/**
 * Disponibilité d'une nuitée — miroir de `GET /stays/{id}/availability`.
 * `booked` liste les intervalles déjà réservés (dates ISO `YYYY-MM-DD`),
 * consommés par le calendrier de la fiche pour griser les jours indisponibles.
 */
export interface StayAvailability {
  stay_id: number;
  booked: BookedRange[];
}

export interface BookedRange {
  start_date: string;
  end_date: string;
}
