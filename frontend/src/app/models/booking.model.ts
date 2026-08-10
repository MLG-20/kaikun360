import { ReviewableType } from '../core/api/review.service';

/**
 * Réservation (polymorphe) — miroir de `BookingResource` (couche transversale).
 *
 * Enrichie en F3.4 : `type`/`type_label` désignent la nature de l'élément
 * réservé (nuitée, véhicule, expérience, trajet), `item_label` en donne un
 * libellé lisible (présent seulement si le backend a chargé la relation), et
 * `cancellable` indique si le client peut encore annuler lui-même (vrai pour les
 * véhicules et expériences non encore annulés uniquement).
 */
export type BookingType =
  | 'stay'
  | 'vehicle'
  | 'experience'
  | 'mobility'
  // F8.11 — prestation sur-mesure née de l'acceptation d'un devis. Elle n'a
  // aucune fiche au catalogue : la cible réservée est le DEVIS lui-même.
  | 'quote'
  // F8.14 — le team building et la construction ont LEURS propres devis : c'est
  // le devis accepté qui est réservé, pas une fiche du catalogue.
  | 'team_building'
  | 'construction'
  | 'autre';

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
  /**
   * F11.5 — le titulaire peut-il ranger cette réservation dans sa corbeille ?
   * ⚠️ Ce n'est PAS le contraire de `cancellable` : les deux peuvent être faux
   * en même temps (une nuitée à venir n'est ni annulable ici, ni rangeable).
   * Décidé par le serveur (terminée ou annulée).
   */
  hideable?: boolean;

  // --- État de règlement (F8.6) ---------------------------------------------
  // Le client pouvait réserver sans jamais pouvoir payer : l'API ne disait pas
  // ce qui restait dû. `paid_xof` ne compte que les paiements ENCAISSÉS — un
  // règlement Wave en attente de confirmation n'a rien apporté.
  paid_xof: number;
  remaining_xof: number;
  is_paid: boolean;
  /** Faux si la réservation est annulée ou déjà soldée. */
  payable: boolean;

  // --- Avis (F8.15.a) --------------------------------------------------------
  // On note la chose réservée (un logement, un véhicule, une expérience), pas la
  // réservation : d'où ce couple, que le front ne peut pas déduire de `id`.
  // `null` pour un trajet et pour le sur-mesure, qui ne se notent pas.
  reviewable_type: ReviewableType | null;
  reviewable_id: number | null;
  /** Vrai si le service est **terminé** — seul cas où le serveur accepte un avis. */
  can_review: boolean;
}
