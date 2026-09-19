import { PropertyPhoto } from './property.model';

/** Un jour du programme d'un circuit (F21). */
export interface ExperienceItineraryDay {
  day: number;
  title: string | null;
  description: string | null;
}

/**
 * Une date de départ d'un circuit, avec ses propres places (F21) — miroir de
 * `ExperienceDepartureResource`. `seats_left` n'est présent que lorsque le
 * contrôleur l'a calculé (fiche détail, disponibilité, supervision).
 */
export interface ExperienceDeparture {
  id: number;
  start_date: string;
  seats_total: number;
  seats_left?: number;
}

/**
 * Expérience touristique — miroir de `ExperienceResource` (module Explore).
 */
export interface Experience {
  id: number;
  reference: string;
  title: string;
  destination: string;
  description: string | null;
  /** Programme jour par jour (F21). */
  itinerary: ExperienceItineraryDay[];
  duration_days: number;
  price_xof: number;
  /** Ce qui est compris dans le prix, en texte libre (F21). */
  included: string | null;
  /** Ce qui n'est PAS compris, en texte libre (F21). */
  excluded: string | null;
  /** Lien Google Maps collé par le prestataire (F5.10), ou `null`. */
  maps_link: string | null;
  status: string | null;
  status_label: string | null;
  published_at: string | null;
  /** Dates de départ du circuit (F21). */
  departures: ExperienceDeparture[];
  /**
   * Compteurs de médias (F8.1), fournis par les listes back-office uniquement.
   *
   * Servent à repérer dans le catalogue de supervision une annonce publiée
   * SANS visuel, ou dont des photos ont été masquées par la modération.
   */
  media_count?: number;

  /**
   * Photos de l'annonce (F8.18), servies dès que la relation est chargée :
   * catalogue public, fiche publique et espace du prestataire.
   */
  photos?: PropertyPhoto[];
  /**
   * URL de la photo de couverture, ou `null` si l'annonce n'est pas encore
   * illustrée — la carte retombe alors sur sa vignette de repli.
   */
  photo_url?: string | null;

  /** Offre déposée par l'équipe Kaikun 360 (et non par un prestataire) : affiche l'étiquette. */
  published_by_kaikun?: boolean;

  media_hidden_count?: number;
}

/**
 * Circuit vu depuis le **back-office** — miroir d'`AdminExperienceResource`
 * (F7.2.k).
 *
 * Sur-ensemble d'`Experience` : ajoute le **remplissage** du circuit (les
 * « capacités groupes » du cahier des charges) et le prestataire opérateur.
 * Servi uniquement par `GET /admin/experiences`.
 *
 * ⚠️ Revu en F21 : `capacity_total`/`seats_taken` sont des CUMULS toutes
 * dates de départ confondues (repère rapide sur la liste) ; le détail par
 * date vit dans `departures`.
 */
export interface AdminExperience extends Experience {
  capacity_total: number;
  seats_taken: number;
  seats_left: number;
  provider?: { id: number; name: string; email: string | null; phone: string | null } | null;
  created_at: string | null;
}

/**
 * Disponibilité d'une expérience — miroir de `GET /experiences/{id}/availability`.
 * Une entrée par date de départ à venir, avec ses places restantes (F21).
 */
export interface ExperienceAvailability {
  experience_id: number;
  departures: ExperienceDeparture[];
}
