import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { environment } from '../../../environments/environment';
import { UserDocument } from '../../models/document.model';
import { User } from '../../models/user.model';
import { ApiEnvelope } from './api-response.model';

/**
 * Corps de `PATCH /users/me` — miroir de `UpdateProfileRequest` (backend).
 *
 * Mise à jour **partielle** : on n'envoie que les champs réellement modifiés.
 * L'e-mail et le téléphone ne sont volontairement PAS modifiables ici (leur
 * changement exige une re-vérification dédiée, à traiter plus tard).
 */
export interface UpdateProfilePayload {
  name?: string;
  city?: string | null;
  preferences?: Record<string, unknown> | null;
}

/**
 * Service de l'espace personnel (F3.2) — compte et pièces justificatives de
 * l'utilisateur connecté. Tous les endpoints sont auto-restreints au porteur du
 * jeton côté backend (routes `/users/me`), aucun risque d'accès aux données
 * d'autrui.
 *
 * Distinct de `AuthService` (qui ne garde en mémoire que l'utilisateur reçu à la
 * connexion) : ici on va **rechercher le profil frais** (`GET /users/me`, avec
 * le profil chargé) et on le met à jour.
 */
@Injectable({ providedIn: 'root' })
export class AccountService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** Profil frais de l'utilisateur connecté (avec `profile` chargé). */
  me(): Observable<User> {
    return this.http
      .get<ApiEnvelope<{ user: User }>>(`${this.api}/users/me`)
      .pipe(map((res) => res.data.user));
  }

  /** Mise à jour partielle du profil. Renvoie l'utilisateur mis à jour. */
  updateProfile(payload: UpdateProfilePayload): Observable<User> {
    return this.http
      .patch<ApiEnvelope<{ user: User }>>(`${this.api}/users/me`, payload)
      .pipe(map((res) => res.data.user));
  }

  /**
   * Suppression du compte (RGPD) : le backend **anonymise** les données
   * personnelles et coupe les accès (réservations/paiements conservés pour la
   * rétention légale). L'appelant doit ensuite vider la session locale.
   */
  deleteAccount(): Observable<void> {
    return this.http.delete<unknown>(`${this.api}/users/me`).pipe(map(() => void 0));
  }

  /** Liste des pièces justificatives déposées (plus récentes d'abord). */
  documents(): Observable<UserDocument[]> {
    return this.http
      .get<ApiEnvelope<{ documents: UserDocument[] }>>(`${this.api}/users/me/documents`)
      .pipe(map((res) => res.data.documents));
  }

  /**
   * Dépôt d'une pièce justificative. Envoi **multipart** (fichier) : on passe un
   * `FormData`, laissant le navigateur poser le bon `Content-Type` et le boundary
   * (ne PAS forcer d'en-tête JSON ici). Renvoie la pièce créée.
   */
  uploadDocument(type: string, file: File): Observable<UserDocument> {
    const form = new FormData();
    form.append('type', type);
    form.append('file', file);
    return this.http
      .post<ApiEnvelope<{ document: UserDocument }>>(`${this.api}/users/me/documents`, form)
      .pipe(map((res) => res.data.document));
  }
}
