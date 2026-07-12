/**
 * Réservation (polymorphe) — miroir de `BookingResource` (couche transversale).
 */
export interface Booking {
  id: number;
  reference: string;
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
}
