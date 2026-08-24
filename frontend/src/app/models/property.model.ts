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
  /** Caution demandée pour une location au mois (F5.8) — montant MENSUEL
   *  déclaré par le propriétaire, indépendant du loyer. Distincte de
   *  `stay.caution_xof` (nuitées). */
  caution_xof: number | null;
  /** Nombre de mois de caution demandés. */
  caution_months: number | null;
  /** Montant TOTAL de la caution, calculé par le backend (`caution_xof × caution_months`). */
  caution_total_xof: number | null;
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

  /**
   * Compteurs de médias (F8.1), fournis par les listes back-office uniquement.
   *
   * Servent à repérer dans le catalogue de supervision une annonce publiée
   * SANS visuel, ou dont des photos ont été masquées par la modération.
   */
  media_count?: number;
  media_hidden_count?: number;
  /**
   * Nombre de pièces justificatives rattachées au bien. Présent uniquement sur
   * la liste « Mes biens » (`GET /properties/mine`, via `withCount`) : alimente
   * le compteur de l'écran « Documents » (F4.5). `undefined` ailleurs.
   */
  documents_count?: number;
  published_at: string | null;
  created_at: string | null;
}

/**
 * Pièce justificative d'un bien — miroir de `PropertyDocumentResource` (Immo).
 *
 * Le chemin de stockage n'est jamais exposé : seul un lien de téléchargement
 * **signé et temporaire** (`download_url`, ~10 min) permet d'accéder au fichier.
 */
export interface PropertyDocument {
  id: number;
  type: string;
  original_name: string | null;
  mime_type: string | null;
  size: number | null;
  validation_status: string | null;
  download_url: string;
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
  /** Lien Google Maps collé par le propriétaire (F5.10), ou `null`. */
  maps_link: string | null;
}

export interface PropertyOwner {
  id: number | null;
  name: string | null;
}
