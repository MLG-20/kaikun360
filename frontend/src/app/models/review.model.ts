/**
 * Avis — miroir de `ReviewResource` (couche transversale). L'auteur n'est
 * présent que lorsqu'il est chargé côté API.
 */
export interface Review {
  id: number;
  reference: string;
  rating: number;
  comment: string | null;
  status: string | null;
  status_label: string | null;
  author?: ReviewAuthor;
  created_at: string | null;
}

export interface ReviewAuthor {
  id: number;
  name: string;
}
