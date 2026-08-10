import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { ApiEnvelope } from './api-response.model';

/** Les cinq types d'annonces qui peuvent aller à la corbeille (F11.4). */
export type TrashType = 'property' | 'stay' | 'vehicle' | 'experience' | 'mobility';

/** Une ligne de corbeille — volontairement pauvre : de quoi reconnaître et décider. */
export interface TrashItem {
  type: TrashType;
  id: number;
  /** Intitulé lisible, calculé côté serveur (les 5 modèles ne le portent pas pareil). */
  label: string;
  deleted_at: string;
  /** Jours restants avant effacement définitif. `0` = aujourd'hui, pas « déjà parti ». */
  days_left: number;
}

export interface TrashContents {
  items: TrashItem[];
  /** Durée de conservation, dictée par le serveur — jamais écrite en dur ici. */
  retention_days: number;
}

/**
 * Corbeille des espaces utilisateurs (F11.4).
 *
 * ⚠️ **Aucune méthode de suppression ici, et c'est voulu** : on ne met rien à
 * la corbeille depuis cet écran. Ranger une annonce reste le geste de son
 * propre écran (« Mes biens », « Mes véhicules »…), qui connaît ses règles et
 * ses refus. Ce service ne sait que **regarder** et **restaurer**.
 */
@Injectable({ providedIn: 'root' })
export class TrashService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** GET /me/trash — tout ce que j'ai rangé, tous types confondus. */
  contents(): Observable<ApiEnvelope<TrashContents>> {
    return this.http.get<ApiEnvelope<TrashContents>>(`${this.api}/me/trash`);
  }

  /**
   * POST /me/trash/{type}/{id}/restore — sort une annonce de la corbeille.
   *
   * ⚠️ Elle revient **hors ligne** : le serveur l'éteint volontairement. Entre
   * sa mise à la corbeille et sa restauration, le bien a pu être vendu ou le
   * prix devenir faux — c'est un geste explicite qui la republie.
   */
  restore(type: TrashType, id: number): Observable<ApiEnvelope<{ item: TrashItem }>> {
    return this.http.post<ApiEnvelope<{ item: TrashItem }>>(
      `${this.api}/me/trash/${type}/${id}/restore`,
      {},
    );
  }
}
