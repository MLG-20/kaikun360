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
 * Synthèse revenus & commissions du prestataire — miroir de
 * `GET /provider-missions/earnings` (F5.3). Montants en francs CFA (XOF),
 * entiers. Le net = montant − commission Kaikun.
 */
export interface ProviderEarnings {
  /** Chiffre d'affaires réalisé (missions terminées). */
  revenu_realise_xof: number;
  /** Commissions Kaikun sur les missions terminées. */
  commission_realisee_xof: number;
  /** Net encaissé par le prestataire (réalisé − commission). */
  net_realise_xof: number;
  /** Nombre de missions terminées. */
  missions_terminees: number;
  /** Chiffre d'affaires engagé mais pas encore encaissé (missions acceptées + en cours). */
  revenu_a_venir_xof: number;
  /** Net attendu sur les missions à venir. */
  net_a_venir_xof: number;
  /** Nombre de missions acceptées ou en cours. */
  missions_a_venir: number;
  /** Nombre de missions affectées en attente de réponse. */
  missions_a_traiter: number;
}

/**
 * Un jour du planning hebdomadaire récurrent (F5.4). `weekday` : 0 = lundi …
 * 6 = dimanche. Heures au format `HH:MM` quand le jour est ouvert, sinon null.
 */
export interface WeeklyAvailability {
  weekday: number;
  is_open: boolean;
  start_time: string | null;
  end_time: string | null;
}

/**
 * Une période d'indisponibilité ponctuelle (F5.4) — miroir de
 * `ProviderUnavailabilityResource`. Dates au format `YYYY-MM-DD` (incluses).
 */
export interface Unavailability {
  id: number;
  start_date: string;
  end_date: string;
  reason: string | null;
}

/**
 * Disponibilités du prestataire — réponse de `GET /providers/availability`
 * (F5.4) : le planning hebdomadaire (toujours 7 jours) et les périodes
 * d'indisponibilité à venir.
 */
export interface ProviderAvailability {
  weekly: WeeklyAvailability[];
  unavailabilities: Unavailability[];
}

/** Corps d'ajout d'une indisponibilité (POST). */
export interface NewUnavailability {
  start_date: string;
  end_date: string;
  reason?: string | null;
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
