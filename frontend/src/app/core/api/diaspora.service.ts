import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { DiasporaProject, DiasporaReport } from '../../models/diaspora.model';
import { ApiEnvelope } from './api-response.model';
import { Paginated } from './pagination.model';
import { environment } from '../../../environments/environment';

/** Type de projet diaspora — miroir de l'enum `DiasporaProjectType`. */
export type DiasporaProjectTypeValue = 'achat' | 'construction' | 'gestion_locative';

/** Priorité — miroir de l'enum `DiasporaPriority`. */
export type DiasporaPriorityValue = 'normale' | 'haute' | 'strategique';

/** Option d'un sélecteur (valeur backend + libellé affiché). */
export interface DiasporaOption<T extends string> {
  value: T;
  label: string;
}

/** Types de projet proposés au dépôt (miroir de `DiasporaProjectType`). */
export const DIASPORA_PROJECT_TYPES: readonly DiasporaOption<DiasporaProjectTypeValue>[] = [
  { value: 'achat', label: 'Achat immobilier' },
  { value: 'construction', label: 'Construction' },
  { value: 'gestion_locative', label: 'Gestion locative' },
];

/** Priorités proposées (miroir de `DiasporaPriority`). */
export const DIASPORA_PRIORITIES: readonly DiasporaOption<DiasporaPriorityValue>[] = [
  { value: 'normale', label: 'Normale' },
  { value: 'haute', label: 'Haute' },
  { value: 'strategique', label: 'Stratégique' },
];

/** Corps de `POST /diaspora-projects` — miroir de `StoreDiasporaProjectRequest`. */
export interface NewDiasporaProjectPayload {
  project_type: DiasporaProjectTypeValue;
  residence_country: string;
  budget_xof?: number | null;
  description?: string | null;
  priority?: DiasporaPriorityValue | null;
}

/**
 * Projets diaspora du **client** (F3.8) — création, liste et suivi enrichi de
 * rapports.
 *
 * Comble l'exigence CDC §15 (« un dossier diaspora peut être créé, suivi et
 * enrichi de rapports ») restée sans interface côté client, alors que le
 * backend l'expose déjà : `POST /diaspora-projects`, `GET /diaspora-projects/mine`,
 * `GET /diaspora-projects/{id}` et `GET /diaspora-projects/{id}/reports`.
 *
 * Les rapports sont déposés par l'**agent affecté** (back-office) ; le client
 * les consulte en lecture seule.
 */
@Injectable({ providedIn: 'root' })
export class DiasporaService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** GET /diaspora-projects/mine — mes projets (paginé, 15/page, avec `reports_count`). */
  myProjects(page = 1): Observable<Paginated<DiasporaProject>> {
    return this.http.get<Paginated<DiasporaProject>>(`${this.api}/diaspora-projects/mine`, {
      params: { page: String(page) },
    });
  }

  /** POST /diaspora-projects — crée un projet (statut initial « nouveau »). */
  createProject(
    payload: NewDiasporaProjectPayload,
  ): Observable<ApiEnvelope<{ project: DiasporaProject }>> {
    return this.http.post<ApiEnvelope<{ project: DiasporaProject }>>(
      `${this.api}/diaspora-projects`,
      this.clean(payload),
    );
  }

  /** GET /diaspora-projects/{id} — détail d'un projet (propriétaire ou agent affecté). */
  project(id: number): Observable<ApiEnvelope<{ project: DiasporaProject }>> {
    return this.http.get<ApiEnvelope<{ project: DiasporaProject }>>(
      `${this.api}/diaspora-projects/${id}`,
    );
  }

  /** GET /diaspora-projects/{id}/reports — rapports de suivi (paginé, plus récents d'abord). */
  reports(id: number, page = 1): Observable<Paginated<DiasporaReport>> {
    return this.http.get<Paginated<DiasporaReport>>(
      `${this.api}/diaspora-projects/${id}/reports`,
      { params: { page: String(page) } },
    );
  }

  /** Prépare le corps : omet les valeurs vides (budget, description, priorité facultatifs). */
  private clean(p: NewDiasporaProjectPayload): Record<string, unknown> {
    const body: Record<string, unknown> = {
      project_type: p.project_type,
      residence_country: p.residence_country.trim(),
    };
    if (p.budget_xof != null) body['budget_xof'] = p.budget_xof;
    if (p.description && p.description.trim() !== '') body['description'] = p.description.trim();
    if (p.priority) body['priority'] = p.priority;
    return body;
  }
}
