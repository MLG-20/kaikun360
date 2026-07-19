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
