import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Observable, finalize, map, tap } from 'rxjs';

import { environment } from '../../../environments/environment';
import { User } from '../../models/user.model';
import { ApiEnvelope } from '../api/api-response.model';
import {
  AuthResult,
  GooglePayload,
  LoginPayload,
  RegisterPayload,
  ResetPasswordPayload,
  VerificationChannel,
} from './auth.types';

/**
 * Service de session (F0.2).
 *
 * Le jeton Sanctum est conservé **en mémoire uniquement** (signal privé), jamais
 * dans le localStorage — exigence de sécurité du cahier des charges. Conséquence
 * assumée : un rafraîchissement de page déconnecte l'utilisateur (une reconnexion
 * silencieuse pourra être ajoutée plus tard si besoin).
 *
 * L'état est exposé via des signals en lecture seule ; le `tokenInterceptor` lit
 * le jeton, l'`errorInterceptor` appelle `clearSession()` sur un 401.
 */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  private readonly tokenSignal = signal<string | null>(null);
  private readonly userSignal = signal<User | null>(null);

  /** Utilisateur connecté (ou null). */
  readonly user = this.userSignal.asReadonly();
  /** Vrai si une session est active. */
  readonly isAuthenticated = computed(() => this.tokenSignal() !== null);

  /** Jeton courant, pour le `tokenInterceptor`. */
  get token(): string | null {
    return this.tokenSignal();
  }

  /** Connexion (e-mail ou téléphone). */
  login(payload: LoginPayload): Observable<User> {
    return this.authenticate(`${this.api}/auth/login`, payload);
  }

  /** Inscription puis ouverture de session. */
  register(payload: RegisterPayload): Observable<User> {
    return this.authenticate(`${this.api}/auth/register`, payload);
  }

  /**
   * Connexion via Google : `idToken` est l'ID token obtenu côté client par
   * Google Identity Services. Le backend le vérifie et renvoie un jeton Sanctum.
   * (Le bouton Google sera branché sur l'écran de connexion en F1.)
   */
  loginWithGoogle(idToken: string): Observable<User> {
    return this.authenticate(`${this.api}/auth/google`, { id_token: idToken });
  }

  /**
   * (Re)envoie un code de vérification sur le canal choisi (F1.3).
   * Nécessite une session active (le compte est déjà identifié).
   */
  sendVerificationCode(channel: VerificationChannel): Observable<void> {
    return this.http
      .post<ApiEnvelope<{ message: string }>>(`${this.api}/auth/verify/send`, { channel })
      .pipe(map(() => void 0));
  }

  /**
   * Vérifie le code saisi et met à jour l'utilisateur en session (statut ACTIF,
   * canal marqué vérifié). Le backend renvoie l'utilisateur mis à jour.
   */
  verify(channel: VerificationChannel, code: string): Observable<User> {
    return this.http.post<ApiEnvelope<{ user: User }>>(`${this.api}/auth/verify`, { channel, code }).pipe(
      map((response) => response.data.user),
      tap((user) => this.userSignal.set(user)),
    );
  }

  /**
   * Demande un code de réinitialisation de mot de passe (F1.3). Endpoint public.
   * La réponse est identique que le compte existe ou non (anti-énumération).
   */
  forgotPassword(login: string): Observable<void> {
    return this.http
      .post<ApiEnvelope<{ message: string }>>(`${this.api}/auth/password/forgot`, { login })
      .pipe(map(() => void 0));
  }

  /** Réinitialise le mot de passe avec le code reçu (F1.3). Endpoint public. */
  resetPassword(payload: ResetPasswordPayload): Observable<void> {
    return this.http
      .post<ApiEnvelope<{ message: string }>>(`${this.api}/auth/password/reset`, payload)
      .pipe(map(() => void 0));
  }

  /** Déconnexion : révoque le jeton côté serveur puis vide la session locale. */
  logout(): Observable<void> {
    return this.http.post<unknown>(`${this.api}/auth/logout`, {}).pipe(
      // La session locale est vidée quoi qu'il arrive (même si l'appel échoue).
      finalize(() => this.clearSession()),
      map(() => void 0),
    );
  }

  /**
   * Remplace l'utilisateur courant en session (sans toucher au jeton). Utilisé
   * après une mise à jour du profil (F3.2) pour que le nom affiché dans l'en-tête
   * reflète immédiatement le changement.
   */
  setCurrentUser(user: User): void {
    this.userSignal.set(user);
  }

  /** Vide l'état de session (appelé aussi par l'errorInterceptor sur un 401). */
  clearSession(): void {
    this.tokenSignal.set(null);
    this.userSignal.set(null);
  }

  /** L'utilisateur possède-t-il ce rôle ? */
  hasRole(role: string): boolean {
    return this.userSignal()?.roles.includes(role) ?? false;
  }

  /** L'utilisateur possède-t-il au moins un des rôles ? */
  hasAnyRole(roles: string[]): boolean {
    return roles.some((role) => this.hasRole(role));
  }

  /** Appel commun login/register/google : POST, puis stockage jeton + utilisateur. */
  private authenticate(
    url: string,
    body: LoginPayload | RegisterPayload | GooglePayload,
  ): Observable<User> {
    return this.http.post<ApiEnvelope<AuthResult>>(url, body).pipe(
      map((response) => response.data),
      tap((result) => {
        this.tokenSignal.set(result.token);
        this.userSignal.set(result.user);
      }),
      map((result) => result.user),
    );
  }
}
