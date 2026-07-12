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
  published_at: string | null;
  created_at: string | null;
}

export interface PropertyLocation {
  region: string | null;
  department: string | null;
  commune: string | null;
  tourist_zone: string | null;
  address: string | null;
  latitude: string | null;
  longitude: string | null;
}

export interface PropertyOwner {
  id: number | null;
  name: string | null;
}
