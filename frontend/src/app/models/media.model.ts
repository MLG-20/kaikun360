/**
 * Média (image / vidéo) — miroir de `MediaResource` (couche transversale).
 */
export interface Media {
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
