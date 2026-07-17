import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { Property } from '../../models/property.model';
import { ApiEnvelope } from './api-response.model';
import { Paginated } from './pagination.model';

/**
 * Accès aux favoris du client connecté (F3.5).
 *
 * Les favoris portent sur des **biens immobiliers** publiés : un utilisateur
 * sauvegarde un bien pour le retrouver plus tard. `myFavorites` liste ses
 * favoris (`GET /favorites`, paginé 15/page, plus récents d'abord) ; `add` et
 * `remove` posent/retirent un bien (`POST/DELETE /properties/{id}/favorite`).
 *
 * Auth requise (Bearer posé par l'intercepteur ; un appel anonyme est détourné
 * vers la connexion). L'ajout est idempotent côté serveur (favoriser deux fois
 * ne crée pas de doublon).
 */
@Injectable({ providedIn: 'root' })
export class FavoriteService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** GET /favorites — les biens mis en favori par l'utilisateur (paginé). */
  myFavorites(page = 1): Observable<Paginated<Property>> {
    return this.http.get<Paginated<Property>>(`${this.api}/favorites`, {
      params: { page: String(page) },
    });
  }

  /** POST /properties/{id}/favorite — ajoute un bien aux favoris (idempotent). */
  add(propertyId: number): Observable<ApiEnvelope<{ message: string }>> {
    return this.http.post<ApiEnvelope<{ message: string }>>(
      `${this.api}/properties/${propertyId}/favorite`,
      {},
    );
  }

  /** DELETE /properties/{id}/favorite — retire un bien des favoris. */
  remove(propertyId: number): Observable<ApiEnvelope<{ message: string }>> {
    return this.http.delete<ApiEnvelope<{ message: string }>>(
      `${this.api}/properties/${propertyId}/favorite`,
    );
  }
}
