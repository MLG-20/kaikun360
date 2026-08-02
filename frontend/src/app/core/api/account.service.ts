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
 * Depuis F3.2b, l'e-mail et le téléphone SONT modifiables — mais les changer
 * déclenche une **re-vérification** du nouveau contact (cf. `ProfileUpdateResult`).
 */
export interface UpdateProfilePayload {
  name?: string;
  email?: string;
  phone?: string | null;
  address?: string | null;
  region_id?: number | null;
  department_id?: number | null;
  commune_id?: number | null;
  city?: string | null;
  preferences?: Record<string, unknown> | null;
}

/**
 * Résultat d'une mise à jour de profil : l'utilisateur à jour + les canaux à
 * re-vérifier (l'e-mail / le téléphone qui viennent d'être changés).
 */
export interface ProfileUpdateResult {
  user: User;
  emailVerificationRequired: boolean;
  phoneVerificationRequired: boolean;
  /**
   * Média réellement employé pour le code du TÉLÉPHONE. Tant qu'aucun
   * fournisseur SMS n'est branché côté backend, le code part par e-mail : la
   * page doit l'annoncer, sinon l'utilisateur guette un SMS qui n'arrivera pas.
   */
  phoneCodeDelivery: 'sms' | 'mail';
}

/** Corps de `PATCH /users/me/password` — miroir de `UpdatePasswordRequest`. */
export interface UpdatePasswordPayload {
  current_password: string;
  password: string;
  password_confirmation: string;
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

  /**
   * Mise à jour partielle du profil. Renvoie l'utilisateur à jour et les canaux
   * à re-vérifier (si l'e-mail / le téléphone ont changé, le backend a envoyé un
   * code au nouveau contact).
   */
  updateProfile(payload: UpdateProfilePayload): Observable<ProfileUpdateResult> {
    return this.http
      .patch<
        ApiEnvelope<{
          user: User;
          verification: {
            email_required: boolean;
            phone_required: boolean;
            phone_delivery: 'sms' | 'mail';
          };
        }>
      >(`${this.api}/users/me`, payload)
      .pipe(
        map((res) => ({
          user: res.data.user,
          emailVerificationRequired: res.data.verification.email_required,
          phoneVerificationRequired: res.data.verification.phone_required,
          // Repli défensif : un backend plus ancien n'enverrait pas le champ.
          phoneCodeDelivery: res.data.verification.phone_delivery ?? 'sms',
        })),
      );
  }

  /** Changement de mot de passe (exige le mot de passe actuel). */
  updatePassword(payload: UpdatePasswordPayload): Observable<void> {
    return this.http
      .patch<ApiEnvelope<{ message: string }>>(`${this.api}/users/me/password`, payload)
      .pipe(map(() => void 0));
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

  /**
   * Dépose (ou remplace) la photo de profil / le logo d'entreprise (F8.0).
   *
   * Envoi **multipart**, champ `avatar` : comme pour les pièces justificatives,
   * on passe un `FormData` sans forcer d'en-tête (le navigateur pose le
   * `Content-Type` et le boundary).
   *
   * Renvoie l'utilisateur COMPLET et non la seule URL : l'appelant le pousse
   * dans `AuthService.setCurrentUser()`, ce qui rafraîchit d'un coup l'en-tête
   * de l'espace et la page profil. Une seule source de vérité pour le compte
   * connecté, pas un champ recollé à la main dans deux états locaux.
   */
  uploadAvatar(file: File): Observable<User> {
    const form = new FormData();
    form.append('avatar', file);
    return this.http
      .post<ApiEnvelope<{ user: User }>>(`${this.api}/users/me/avatar`, form)
      .pipe(map((res) => res.data.user));
  }

  /** Retire la photo / le logo. Idempotent côté backend. */
  deleteAvatar(): Observable<User> {
    return this.http
      .delete<ApiEnvelope<{ user: User }>>(`${this.api}/users/me/avatar`)
      .pipe(map((res) => res.data.user));
  }
}
