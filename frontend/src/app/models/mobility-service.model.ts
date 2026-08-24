import { PropertyPhoto } from './property.model';

/**
 * Service de mobilité (navette, transfert, liaison, excursion) —
 * miroir de `MobilityServiceResource` (module Mobility).
 */
export interface MobilityService {
  id: number;
  reference: string;
  type: string | null;
  type_label: string | null;
  departure: string;
  destination: string;
  departure_at: string | null;
  capacity: number;
  price_xof: number;
  description: string | null;
  /** Lien Google Maps du point de départ, collé par le prestataire (F5.10). */
  maps_link: string | null;
  status: string | null;
  status_label: string | null;
  /**
   * Véhicule qui opère le départ (F8.23), ou `null`. Sert au **préremplissage**
   * du formulaire de correction : sans lui, corriger un prix détacherait le
   * départ de son véhicule — donc de ses photos et de sa conformité.
   */
  vehicle_id?: number | null;
  /**
   * Photos du trajet (F8.18) — ce sont celles du **véhicule qui l'opère** :
   * `mobility_services.vehicle_id`. Le prestataire n'illustre pas chaque départ,
   * il illustre son véhicule une fois. Vide si aucun véhicule n'est rattaché.
   */
  photos?: PropertyPhoto[];
  /** Couverture héritée du véhicule, ou `null` (vignette de repli). */
  photo_url?: string | null;
}

/**
 * Fiche publique d'un départ (`GET /mobility-services/{id}`, F8.10) : le trajet
 * et son **remplissage**. Les places restantes viennent du serveur — les
 * calculer côté client demanderait de connaître toutes les réservations.
 */
export interface MobilityServiceDetail {
  mobility_service: MobilityService;
  seats_taken: number;
  seats_left: number;
}

/**
 * Trajet vu depuis le **back-office** — miroir d'`AdminMobilityServiceResource`
 * (F7.2.j).
 *
 * Sur-ensemble de `MobilityService` : ajoute le **remplissage** du départ (les
 * « disponibilités » du cahier des charges), le véhicule affecté et le
 * prestataire opérateur. Servi uniquement par `GET /admin/mobility-services`.
 */
export interface AdminMobilityService extends MobilityService {
  /** Places déjà réservées (réservations annulées exclues). */
  seats_taken: number;
  /** Places restantes = capacité − places prises (jamais négatif). */
  seats_left: number;
  /** Véhicule rattaché (un trajet peut être annoncé sans véhicule nommé). */
  vehicle?: {
    id: number;
    reference: string;
    label: string | null;
    type_label: string | null;
    capacity: number;
  } | null;
  provider?: { id: number; name: string; email: string | null; phone: string | null } | null;
  created_at: string | null;
}
