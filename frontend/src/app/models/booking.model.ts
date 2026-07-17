/**
 * Réservation (polymorphe) — miroir de `BookingResource` (couche transversale).
 *
 * Enrichie en F3.4 : `type`/`type_label` désignent la nature de l'élément
 * réservé (nuitée, véhicule, expérience, trajet), `item_label` en donne un
 * libellé lisible (présent seulement si le backend a chargé la relation), et
 * `cancellable` indique si le client peut encore annuler lui-même (vrai pour les
 * véhicules et expériences non encore annulés uniquement).
 */
export type BookingType = 'stay' | 'vehicle' | 'experience' | 'mobility' | 'autre';

export interface Booking {
  id: number;
  reference: string;
  type: BookingType;
  type_label: string;
  item_label?: string | null;
  status: string | null;
  status_label: string | null;
  start_date: string | null;
  end_date: string | null;
  guests: number | null;
  amount_xof: number | null;
  commission_xof: number | null;
  caution_xof: number | null;
  caution_status: string | null;
  cancelled_at: string | null;
  created_at: string | null;
  cancellable: boolean;
}
