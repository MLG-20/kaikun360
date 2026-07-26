/**
 * Projet diaspora — miroir de `DiasporaProjectResource` (module Diaspora).
 *
 * Un dossier piloté à distance (achat, construction ou gestion locative) suivi
 * par un agent Kaikun et enrichi de **rapports** datés (photos / vidéo).
 * `reports_count` n'est présent que sur la liste `GET /diaspora-projects/mine`.
 */
export interface DiasporaProject {
  id: number;
  reference: string;
  project_type: string | null;
  project_type_label: string | null;
  residence_country: string;
  budget_xof: number | null;
  description: string | null;
  priority: string | null;
  priority_label: string | null;
  status: string | null;
  status_label: string | null;
  agent_id: number | null;
  reports_count?: number;
}

/**
 * Rapport de suivi — miroir de `ReportResource` (transversal, module Build,
 * partagé avec Diaspora). Déposé par l'agent affecté ; consulté (lecture seule)
 * par le client dans le détail de son projet.
 */
export interface DiasporaReport {
  id: number;
  reference: string;
  type: string | null;
  type_label: string | null;
  /** URLs des photos (tableau vide si aucune). */
  photos: string[];
  video_url: string | null;
  comment: string | null;
  reported_at: string | null;
}
