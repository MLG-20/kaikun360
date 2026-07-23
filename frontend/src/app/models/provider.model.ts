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
 * Valeurs possibles du statut d'une mission, miroir de l'enum `MissionStatus`
 * backend. Cycle : `affectee` → `acceptee` → `en_cours` → `terminee`
 * (`refusee` si le prestataire décline ; `annulee` côté plateforme).
 */
export type MissionStatusValue =
  | 'affectee'
  | 'acceptee'
  | 'en_cours'
  | 'terminee'
  | 'refusee'
  | 'annulee';

/**
 * Mission affectée à un prestataire — miroir de `ProviderMissionResource`.
 *
 * `amount_xof` est le montant total de la mission ; `commission_xof` la part
 * prélevée par Kaikun. Le net revenant au prestataire = `amount_xof − commission_xof`.
 */
export interface ProviderMission {
  id: number;
  /** Référence lisible (ex. « MSN-AB12CD34 »). */
  reference: string;
  provider_id: number;
  title: string;
  description: string | null;
  /** Montant total de la mission (FCFA, entier). */
  amount_xof: number;
  /** Commission prélevée par Kaikun (FCFA, entier). */
  commission_xof: number;
  /** Statut technique (`affectee`, `acceptee`…). */
  status: MissionStatusValue;
  /** Libellé lisible du statut (fourni par le serveur). */
  status_label: string;
  /** Date/heure prévue de la mission (ISO 8601) si renseignée. */
  scheduled_at: string | null;
}

/**
 * Action de transition applicable à une mission par le prestataire, alignée sur
 * `PATCH /provider-missions/{mission}/{action}` (backend).
 */
export type MissionAction = 'accept' | 'refuse' | 'start' | 'complete';

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
