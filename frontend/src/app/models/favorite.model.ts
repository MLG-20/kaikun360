/**
 * Favoris POLYMORPHES (tous univers) — miroir de `FavoriteResource` (backend).
 *
 * Un favori pointe vers un élément d'un univers donné (`type`) et embarque cet
 * élément (`favoritable`) rendu par la ressource de son univers — soit la même
 * forme que dans le catalogue, ce qui permet de réutiliser le mapping « carte »
 * (`UNIVERSES[...].toCard`).
 */

/** Univers favorisables (slugs stables partagés avec l'API). */
export type FavoritableType = 'property' | 'stay' | 'vehicle' | 'experience' | 'mobility';

/** Référence légère vers un élément favorisable (portée par une carte de catalogue). */
export interface FavoritableRef {
  type: FavoritableType;
  id: number;
}

/** Un favori de l'utilisateur, avec l'élément favorisé embarqué. */
export interface FavoriteItem {
  /** Identifiant du favori (ligne `favorites`). */
  id: number;
  /** Univers de l'élément favorisé. */
  type: FavoritableType;
  /** Horodatage d'ajout. */
  created_at: string | null;
  /**
   * L'élément favorisé, rendu par la ressource de son univers (Property, Stay,
   * Vehicle, Experience, MobilityService). Typé `unknown` car il varie selon
   * `type` ; on le passe au `toCard` de l'univers correspondant.
   */
  favoritable: unknown;
}

/** Identifiants favoris regroupés par type (pour marquer les cœurs du catalogue). */
export type FavoriteIds = Record<FavoritableType, number[]>;
