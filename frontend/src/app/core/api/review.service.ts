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

/** Un de MES avis (`GET /reviews/mine`) : la note + ce qu'elle vise. */
export interface MyReview extends Review {
  /** Type de la cible notée, en clé courte (jamais le nom de classe PHP). */
  reviewable_type: ReviewableType | null;
  reviewable_id: number | null;
}

/** Corps de `POST /reviews` — miroir de `StoreReviewRequest`. */
export interface ReviewSubmission {
  reviewable_type: ReviewableType;
  reviewable_id: number;
  /** Note de 1 à 5. */
  rating: number;
  comment?: string | null;
}

/**
 * Avis : lecture publique (F2.3) et **dépôt** (F8.15.a).
 *
 * `GET /reviews` est public et exige le couple (`reviewable_type`,
 * `reviewable_id`). N'expose que les avis publiés (modération côté backend).
 *
 * ⚠️ **`GET /reviews` ne suffit pas à savoir si j'ai déjà donné mon avis** : il
 * ne renvoie que les avis **publiés**, or tout avis frais est en modération. Un
 * écran qui s'y fierait rouvrirait le formulaire à un client qui vient d'écrire,
 * pour l'envoyer droit sur le 422 « Vous avez déjà laissé un avis ». D'où
 * `mine()`, qui voit aussi les avis en attente.
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

  /** GET /reviews/mine — mes avis, publiés ET en attente de modération. */
  mine(): Observable<ApiEnvelope<{ reviews: MyReview[] }>> {
    return this.http.get<ApiEnvelope<{ reviews: MyReview[] }>>(`${this.api}/reviews/mine`);
  }

  /**
   * POST /reviews — dépose un avis. Le serveur exige d'avoir consommé la cible
   * (réservation **terminée**) et n'en accepte qu'un seul par cible ; l'avis naît
   * « en attente » et n'est visible qu'après modération.
   */
  submit(body: ReviewSubmission): Observable<ApiEnvelope<{ review: Review }>> {
    return this.http.post<ApiEnvelope<{ review: Review }>>(`${this.api}/reviews`, body);
  }
}
