/**
 * Valeurs possibles du statut de validation d'un prestataire, miroir de l'enum
 * `ProviderStatus` backend. Le champ `Provider.status` reste typé largement
 * (`string | null`, tel que renvoyé par l'API) ; ce type sert aux traitements
 * qui raisonnent sur les valeurs connues (tonalité, message de statut).
 */
export type ProviderStatusValue = 'en_attente' | 'valide' | 'refuse' | 'suspendu';

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
