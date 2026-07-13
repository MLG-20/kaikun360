/**
 * Enveloppe de pagination renvoyée par les collections de ressources Laravel
 * (F2.1). Toutes les listes du catalogue public arrivent sous cette forme :
 *
 *   { data: [...], links: { first, last, prev, next }, meta: { ... } }
 *
 * `T` est le type des éléments (Property, Stay, Vehicle, …).
 */
export interface Paginated<T> {
  data: T[];
  links: PageLinks;
  meta: PageMeta;
}

/** Liens de navigation absolus renvoyés par Laravel (null aux extrémités). */
export interface PageLinks {
  first: string | null;
  last: string | null;
  prev: string | null;
  next: string | null;
}

/** Métadonnées de pagination (numéros de page, total, etc.). */
export interface PageMeta {
  current_page: number;
  from: number | null;
  last_page: number;
  per_page: number;
  to: number | null;
  total: number;
  path: string;
}
