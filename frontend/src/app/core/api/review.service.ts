import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { Review } from '../../models/review.model';
import { ApiEnvelope } from './api-response.model';

/**
 * Type d'entité notée — miroir des clés de `Review::TYPES` backend.
 * Seuls ces univers portent des avis (les biens immobiliers, eux, n'en ont pas).
 */
export type ReviewableType = 'stay' | 'vehicle' | 'experience';

/** Synthèse des notes publiées d'une entité (moyenne + nombre). */
export interface ReviewSummary {
  average: number;
  count: number;
}

/** Réponse de `GET /reviews` : avis publiés + synthèse. */
export interface ReviewList {
  reviews: Review[];
  summary: ReviewSummary;
}

/**
 * Accès en LECTURE aux avis publiés (F2.3).
 *
 * `GET /reviews` est public et exige le couple (`reviewable_type`,
 * `reviewable_id`). N'expose que les avis publiés (modération côté backend).
 */
@Injectable({ providedIn: 'root' })
export class ReviewService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** GET /reviews — avis publiés d'une entité + note moyenne. */
  forEntity(type: ReviewableType, id: number | string): Observable<ApiEnvelope<ReviewList>> {
    const params = new HttpParams()
      .set('reviewable_type', type)
      .set('reviewable_id', String(id));
    return this.http.get<ApiEnvelope<ReviewList>>(`${this.api}/reviews`, { params });
  }
}
