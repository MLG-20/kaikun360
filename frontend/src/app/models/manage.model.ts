import { Property } from './property.model';

/**
 * Tableau de bord de gestion locative du propriétaire connecté (F4.1).
 *
 * Miroir de la réponse de `GET /api/v1/manage/dashboard` (module Manage) :
 * agrégats financiers de TOUS les mandats du propriétaire (loyers, dépenses,
 * reversements) et compteurs d'activité (mandats actifs, incidents ouverts).
 * Tous les montants sont en francs CFA (XOF), entiers.
 */
export interface OwnerDashboard {
  /** Nombre de mandats de gestion locative actuellement actifs. */
  mandats_actifs: number;
  /** Total des loyers encaissés (statut « payé »). */
  loyers_payes_xof: number;
  /** Total des loyers en attente ou en retard. */
  loyers_impayes_xof: number;
  /** Total des dépenses engagées sur les biens gérés (maintenance, réparations…). */
  depenses_xof: number;
  /** Total des reversements déjà effectués au propriétaire. */
  reversements_xof: number;
  /** Nombre d'incidents encore ouverts sur les biens gérés. */
  incidents_ouverts: number;
}

/**
 * Agrégats financiers d'UN mandat, calculés côté serveur (F4.4). Présents dans
 * la liste comme sur la fiche. Montants en francs CFA (XOF), entiers.
 */
export interface MandateSummary {
  loyers_payes_xof: number;
  loyers_impayes_xof: number;
  /** Nombre d'échéances réglées / impayées (désambiguïse deux mois au même loyer). */
  loyers_payes_count: number;
  loyers_impayes_count: number;
  depenses_xof: number;
  reversements_xof: number;
  incidents_ouverts: number;
}

/**
 * Échéance de loyer d'un mandat (F4.4) — miroir de `RentResource`.
 * `status` : `paye` | `impaye` | `en_retard` (libellé fourni par le serveur).
 */
export interface Rent {
  id: number;
  mandate_id: number;
  tenant_name: string | null;
  period_label: string | null;
  due_date: string | null;
  amount_xof: number;
  status: string | null;
  status_label: string | null;
  paid_at: string | null;
}

/**
 * Reversement au propriétaire (F4.4) — miroir de `OwnerPayoutResource`.
 * `status` : `effectue` | `en_attente` (libellé fourni par le serveur).
 */
export interface OwnerPayout {
  id: number;
  reference: string | null;
  mandate_id: number;
  period_label: string | null;
  amount_xof: number;
  status: string | null;
  status_label: string | null;
  paid_at: string | null;
}

/**
 * Incident survenu sur le bien géré (F4.4) — miroir de `IncidentResource`.
 * `status` : `ouvert` | `en_cours` | `resolu` (libellé fourni par le serveur).
 */
export interface Incident {
  id: number;
  reference: string | null;
  title: string | null;
  description: string | null;
  priority: string | null;
  status: string | null;
  status_label: string | null;
  resolved_at: string | null;
}

/**
 * Mandat de gestion locative (F4.4) — miroir de `MandateResource`.
 *
 * Les lignes détaillées (`rents`/`payouts`/`incidents`) ne sont présentes que
 * sur la FICHE (`GET /manage/mandates/{id}`), pas dans la liste `mine` (qui
 * reste légère : seulement le bien + les agrégats `summary`).
 */
export interface Mandate {
  id: number;
  reference: string | null;
  commission_rate: number | string;
  status: string | null;
  status_label: string | null;
  start_date: string | null;
  end_date: string | null;
  summary: MandateSummary;
  property?: Property;
  rents?: Rent[];
  payouts?: OwnerPayout[];
  incidents?: Incident[];
}

/**
 * Rapport mensuel d'un mandat (F4.4) — miroir de `ManagementReportService`.
 * Renvoyé par `GET /manage/mandates/{id}/report?month=YYYY-MM`. Toutes les
 * sommes sont bornées au mois calendaire visé.
 */
export interface MonthlyReport {
  mandate: {
    id: number;
    reference: string | null;
    commission_rate: number | string;
  };
  period: {
    /** Mois ciblé, format `YYYY-MM`. */
    month: string;
    /** Libellé français du mois (ex. « juillet 2026 »). */
    label: string;
  };
  rents: {
    paid_xof: number;
    unpaid_xof: number;
    count: number;
  };
  expenses: {
    total_xof: number;
    count: number;
  };
  /** Commission Kaikun sur les loyers encaissés. */
  commission_xof: number;
  /** Net dû au propriétaire (loyers encaissés − commission − dépenses). */
  net_owner_xof: number;
  payouts: {
    total_xof: number;
    count: number;
  };
  incidents: {
    opened: number;
    resolved: number;
  };
}
