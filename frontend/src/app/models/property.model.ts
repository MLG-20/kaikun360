import type { Stay } from './stay.model';

/**
 * Bien immobilier — miroir de `PropertyResource` (module Immo).
 * Montants en FCFA (entiers). Les coordonnées sont des décimales sérialisées
 * en chaîne par Laravel (cast `decimal`).
 */
export interface Property {
  id: number;
  title: string;
  description: string | null;
  type: string | null;
  type_label: string | null;
  price_xof: number | null;
  status: string | null;
  verification_level: string | null;
  location: PropertyLocation;
  owner: PropertyOwner;
  /**
   * Config « nuitées » du bien (mode courte durée). Présente uniquement sur la
   * gestion privée (fiche propriétaire `GET /properties/mine/{id}`) : `undefined`
   * côté catalogue public (clé absente), `null` si le bien n'est pas en nuitées,
   * l'objet `Stay` sinon. Sert à déduire le mode de location mensuelle/nuitées/mixte.
   */
  stay?: Stay | null;
  /**
   * Photos du bien, **image principale d'abord** puis ordre choisi par le
   * propriétaire. Présentes dès que l'API charge la relation (catalogue, fiche
   * publique, gestion privée).
   */
  photos?: PropertyPhoto[];
  /**
   * URL de la photo de couverture (la principale), ou `null` si le bien n'a pas
   * encore de photo — le front retombe alors sur sa vignette de repli.
   */
  photo_url?: string | null;
  published_at: string | null;
  created_at: string | null;
}

/** Photo d'un bien — miroir de `MediaResource` (couche transversale). */
export interface PropertyPhoto {
  id: number;
  reference: string;
  type: string | null;
  type_label: string | null;
  url: string | null;
  is_primary: boolean;
  position: number;
  status: string | null;
  original_name: string | null;
  size_bytes: number | null;
}

export interface PropertyLocation {
  region: string | null;
  department: string | null;
  commune: string | null;
  /** Identifiants bruts du référentiel — servent à préremplir le formulaire d'édition (F4.3). */
  region_id: number | null;
  department_id: number | null;
  commune_id: number | null;
  tourist_zone: string | null;
  address: string | null;
  latitude: string | null;
  longitude: string | null;
}

export interface PropertyOwner {
  id: number | null;
  name: string | null;
}
