import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { FavoritableType, FavoriteIds, FavoriteItem } from '../../models/favorite.model';
import { ApiEnvelope } from './api-response.model';
import { Paginated } from './pagination.model';

/**
 * Accès aux favoris POLYMORPHES du client connecté (tous univers).
 *
 * Les favoris portent sur n'importe quel élément favorisable (bien, nuitée,
 * véhicule, expérience, service de mobilité) :
 *   - `myFavorites` liste les favoris (paginé, éléments embarqués) ;
 *   - `ids` renvoie les ids favoris regroupés par type, pour marquer d'un cœur
 *     plein les éléments déjà favorisés dans le catalogue (une requête, pas une
 *     par carte) ;
 *   - `add` / `remove` posent/retirent un favori par `{ type, id }`.
 *
 * Auth requise (Bearer posé par l'intercepteur). L'ajout est idempotent côté
 * serveur ; on ne peut favoriser qu'un élément publié / réservable.
 */
@Injectable({ providedIn: 'root' })
export class FavoriteService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** GET /favorites — mes favoris, tous univers confondus (paginé). */
  myFavorites(page = 1): Observable<Paginated<FavoriteItem>> {
    return this.http.get<Paginated<FavoriteItem>>(`${this.api}/favorites`, {
      params: { page: String(page) },
    });
  }

  /** GET /favorites/ids — ids favoris regroupés par type (marquage des cœurs). */
  ids(): Observable<ApiEnvelope<FavoriteIds>> {
    return this.http.get<ApiEnvelope<FavoriteIds>>(`${this.api}/favorites/ids`);
  }

  /** POST /favorites — ajoute un élément aux favoris (idempotent). */
  add(type: FavoritableType, id: number): Observable<ApiEnvelope<{ message: string }>> {
    return this.http.post<ApiEnvelope<{ message: string }>>(`${this.api}/favorites`, { type, id });
  }

  /** DELETE /favorites/{type}/{id} — retire un élément des favoris. */
  remove(type: FavoritableType, id: number): Observable<ApiEnvelope<{ message: string }>> {
    return this.http.delete<ApiEnvelope<{ message: string }>>(`${this.api}/favorites/${type}/${id}`);
  }
}
