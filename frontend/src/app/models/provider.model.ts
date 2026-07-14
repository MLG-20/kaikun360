/**
 * Certification prestataire — miroir de `ProviderCertificationResource`.
 */
export interface ProviderCertification {
  id: number;
  name: string;
  issuer: string | null;
  verified: boolean;
}

/**
 * Prestataire marketplace — miroir de `ProviderResource` (module Pro).
 *
 * `status` suit l'enum `ProviderStatus` backend :
 * `en_attente` → `valide` / `refuse` / `suspendu`. Un prestataire ne publie de
 * service public qu'une fois `valide`.
 */
export interface Provider {
  id: number;
  business_name: string;
  category: string | null;
  category_label: string | null;
  bio: string | null;
  status: string | null;
  status_label: string | null;
  validated_at: string | null;
  warnings_count: number;
  sanction_note: string | null;
  rating_avg: number | null;
  rating_count: number | null;
  /** Présent uniquement si chargé côté backend (->load('certifications')). */
  certifications?: ProviderCertification[];
}
