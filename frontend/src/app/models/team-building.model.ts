/**
 * Modèles de l'espace entreprise / team building (F6) — miroirs des ressources
 * `TeamBuildingRequestResource` et `TeamBuildingQuoteResource` du module backend
 * Team Building (B9). Une entreprise dépose une **demande** de pack groupe ;
 * Kaikun compose puis envoie un ou plusieurs **devis** multi-prestataires que
 * l'entreprise peut accepter.
 */

/** Statut d'une demande — miroir de l'enum `TeamBuildingRequestStatus`. */
export type TeamBuildingRequestStatus =
  | 'nouveau'
  | 'en_etude'
  | 'devis_envoye'
  | 'accepte'
  | 'annule';

/** Statut d'un devis — miroir de l'enum `TeamBuildingQuoteStatus`. */
export type TeamBuildingQuoteStatus = 'brouillon' | 'envoye' | 'accepte' | 'refuse';

/**
 * Une ligne d'un devis composé (une prestation agrégée d'un module).
 * Miroir des lignes produites par `TeamBuildingQuoteComposer::buildLines`.
 */
export interface TeamBuildingQuoteLine {
  category: string;
  label: string;
  module: string | null;
  quantity: number;
  unit_price_xof: number;
  amount_xof: number;
}

/** Un devis composé par le back-office pour une demande. */
export interface TeamBuildingQuote {
  id: number;
  reference: string;
  request_id: number;
  lines: TeamBuildingQuoteLine[];
  subtotal_xof: number;
  /** Taux de marge appliqué, en pourcentage (ex. `"15.00"`). */
  margin_rate: string | number;
  margin_xof: number;
  total_xof: number;
  status: TeamBuildingQuoteStatus | null;
  status_label: string | null;
  sent_at: string | null;
  accepted_at: string | null;
}

/**
 * Besoins structurés d'une demande (`needs`). Chaque clé correspond à une
 * catégorie de composant que l'admin agrège au devis (miroir de l'enum
 * `QuoteLineCategory`, hors `lieu` qui découle du choix de ville).
 */
export interface TeamBuildingNeeds {
  hebergement?: boolean;
  restauration?: boolean;
  activite?: boolean;
  mobilite?: boolean;
  animation?: boolean;
}

/** Une demande de team building déposée par l'entreprise. */
export interface TeamBuildingRequest {
  id: number;
  reference: string;
  participants: number;
  city: string;
  start_date: string | null;
  end_date: string | null;
  budget_xof: number | null;
  needs: TeamBuildingNeeds;
  description: string | null;
  status: TeamBuildingRequestStatus | null;
  status_label: string | null;
  /** Devis rattachés — présents seulement sur le détail (`show`). */
  quotes?: TeamBuildingQuote[];
}
