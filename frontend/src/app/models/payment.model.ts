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
  /** Nature du règlement (F7.3.h) : acompte, solde ou paiement intégral. */
  kind: 'integral' | 'acompte' | 'solde' | null;
  kind_label: string | null;
  status: string | null;
  status_label: string | null;
  mode: string | null;
  /**
   * État de règlement de la réservation rattachée (F7.3.h) — présent quand le
   * serveur a chargé la relation (liste du back-office). C'est là que se lit le
   * « solde » : ce qui a été encaissé et ce qu'il reste à percevoir.
   */
  booking?: {
    reference: string;
    amount_xof: number;
    paid_xof: number;
    remaining_xof: number;
  };
  created_at: string | null;
}
