/**
 * Paiement — miroir de `PaymentResource` (module Paiement, PayTech).
 */
export interface Payment {
  id: number;
  reference: string;
  booking_id: number | null;
  provider: string | null;
  amount_xof: number;
  commission_xof: number | null;
  status: string | null;
  status_label: string | null;
  mode: string | null;
  created_at: string | null;
}
