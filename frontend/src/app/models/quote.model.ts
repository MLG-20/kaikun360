/**
 * Devis — miroir de `QuoteResource` (couche transversale).
 */
export interface Quote {
  id: number;
  reference: string;
  request_id: number;
  amount_xof: number;
  details: Record<string, unknown> | unknown[];
  valid_until: string | null;
  status: string | null;
  status_label: string | null;
}
