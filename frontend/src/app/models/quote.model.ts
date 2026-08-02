/**
 * Interlocuteur humain d'un devis — l'agent qui l'a composé (F8.11).
 *
 * Sur du sur-mesure, le client n'achète pas un article de catalogue : il
 * accorde sa confiance à quelqu'un. Un chiffrage qui arrive sans aucun nom
 * refroidit. Nullable : les devis antérieurs à F8.11 n'ont pas d'auteur connu,
 * les écrans doivent donc tolérer son absence.
 */
export interface QuoteAgent {
  name: string;
  phone: string | null;
  email: string | null;
}

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
  /** L'agent qui suit le dossier (F8.11) — absent des devis anciens. */
  agent?: QuoteAgent | null;
  /**
   * Réservation née de l'acceptation (F8.11) — c'est elle que la page de
   * règlement attend. Absente tant que le devis n'est pas accepté.
   */
  booking_id?: number | null;
}
