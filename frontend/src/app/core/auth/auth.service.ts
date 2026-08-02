import { isPlatformBrowser } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Injectable, PLATFORM_ID, computed, inject, signal } from '@angular/core';
import { Observable, finalize, map, tap } from 'rxjs';

import { environment } from '../../../environments/environment';
import { User } from '../../models/user.model';
import { ApiEnvelope } from '../api/api-response.model';
import {
  AuthResult,
  CodeDelivery,
  GooglePayload,
  LoginOutcome,
  LoginPayload,
  RegisterPayload,
  ResetPasswordPayload,
  TwoFactorChallenge,
  TwoFactorPayload,
  VerificationChannel,
} from './auth.types';

/** Clé de `sessionStorage` où la session (jeton + utilisateur) est conservée. */
const SESSION_KEY = 'kaikun.session';

/**
 * Service de session (F0.2).
 *
 * Le jeton Sanctum est conservé dans le **`sessionStorage`** : il survit à un
 * **rafraîchissement de page** (l'utilisateur reste dans son espace) mais est
 * effacé à la fermeture de l'onglet/navigateur — jamais dans le `localStorage`,
 * donc rien n'est persisté sur le disque entre deux sessions de navigation.
 *
 * Au démarrage (navigateur uniquement), la session est **réhydratée** depuis le
 * `sessionStorage` de façon synchrone — pour que les guards de route voient
 * l'état dès le premier rendu client — puis **revalidée** en arrière-plan via
 * `GET /users/me` (un jeton révoqué déclenche un 401 → `clearSession`).
 *
 * L'état est exposé via des signals en lecture seule ; le `tokenInterceptor` lit
 * le jeton, l'`errorInterceptor` appelle `clearSession()` sur un 401.
 */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;
  private readonly isBrowser = isPlatformBrowser(inject(PLATFORM_ID));

  private readonly tokenSignal = signal<string | null>(null);
  private readonly userSignal = signal<User | null>(null);

  constructor() {
    this.restoreSession();
  }

  /** Utilisateur connecté (ou null). */
  readonly user = this.userSignal.asReadonly();
  /** Vrai si une session est active. */
  readonly isAuthenticated = computed(() => this.tokenSignal() !== null);

  /** Jeton courant, pour le `tokenInterceptor`. */
  get token(): string | null {
    return this.tokenSignal();
  }

  /**
   * Connexion (e-mail ou téléphone).
   *
   * Renvoie un **résultat discriminé** : soit la session est ouverte
   * (`authenticated`), soit le compte est soumis à la double authentification
   * (`two_factor`, comptes admin/super_admin) — auquel cas AUCUN jeton n'est
   * stocké et le frontend doit résoudre le défi via {@link twoFactor}.
   */
  login(payload: LoginPayload): Observable<LoginOutcome> {
    return this.http
      .post<ApiEnvelope<AuthResult | TwoFactorChallenge>>(`${this.api}/auth/login`, payload)
      .pipe(
        map((response): LoginOutcome => {
          const data = response.data;

          // Session normale : la présence d'un jeton distingue ce cas du défi 2FA.
          if ('token' in data) {
            this.tokenSignal.set(data.token);
            this.userSignal.set(data.user);
            this.persist();
            return { kind: 'authenticated', user: data.user };
          }

          // Défi 2FA : pas de jeton, on remonte le challenge au composant.
          return { kind: 'two_factor', login: data.login, channel: data.channel };
        }),
      );
  }

  /**
   * Résout le second facteur du back-office (F7.1.d) : envoie le code reçu par
   * e-mail et ouvre la session (jeton à expiration courte côté serveur).
   */
  twoFactor(payload: TwoFactorPayload): Observable<User> {
    return this.authenticate(`${this.api}/auth/two-factor`, payload);
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
  sendVerificationCode(channel: VerificationChannel): Observable<CodeDelivery> {
    return this.http
      .post<ApiEnvelope<{ message: string; delivery: CodeDelivery }>>(`${this.api}/auth/verify/send`, { channel })
      // Le canal DEMANDÉ n'est pas toujours le média EMPLOYÉ : tant qu'aucun
      // fournisseur SMS n'est branché, un code « téléphone » part par e-mail.
      // On propage le média réel pour que l'écran dise où chercher le code.
      .pipe(map((response) => response.data.delivery ?? (channel === 'phone' ? 'sms' : 'mail')));
  }

  /**
   * Vérifie le code saisi et met à jour l'utilisateur en session (statut ACTIF,
   * canal marqué vérifié). Le backend renvoie l'utilisateur mis à jour.
   */
  verify(channel: VerificationChannel, code: string): Observable<User> {
    return this.http.post<ApiEnvelope<{ user: User }>>(`${this.api}/auth/verify`, { channel, code }).pipe(
      map((response) => response.data.user),
      tap((user) => this.setCurrentUser(user)),
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
    // Garde la copie persistée à jour (nom d'en-tête, statut de vérification…).
    this.persist();
  }

  /** Vide l'état de session (appelé aussi par l'errorInterceptor sur un 401). */
  clearSession(): void {
    this.tokenSignal.set(null);
    this.userSignal.set(null);
    if (this.isBrowser) {
      sessionStorage.removeItem(SESSION_KEY);
    }
  }

  /** L'utilisateur possède-t-il ce rôle ? */
  hasRole(role: string): boolean {
    return this.userSignal()?.roles.includes(role) ?? false;
  }

  /** L'utilisateur possède-t-il au moins un des rôles ? */
  hasAnyRole(roles: string[]): boolean {
    return roles.some((role) => this.hasRole(role));
  }

  /**
   * L'utilisateur détient-il cette permission back-office ? (F7.4.a)
   *
   * Le tableau `permissions` n'est renseigné par le serveur que pour l'équipe,
   * et uniquement sur son propre compte (cf. `UserResource`). Pour tout autre
   * profil il est absent → `false`, ce qui est le bon défaut.
   *
   * ⚠️ Confort d'affichage, PAS un contrôle de sécurité : la vérité reste le
   * `can:` de chaque route serveur. On s'en sert pour ne pas proposer une
   * rubrique qui répondrait 403.
   */
  hasPermission(permission: string): boolean {
    return this.userSignal()?.permissions?.includes(permission) ?? false;
  }

  /** L'utilisateur détient-il au moins une des permissions ? */
  hasAnyPermission(permissions: string[]): boolean {
    return permissions.some((permission) => this.hasPermission(permission));
  }

  /** Appel commun login/register/google : POST, puis stockage jeton + utilisateur. */
  private authenticate(
    url: string,
    body: LoginPayload | RegisterPayload | GooglePayload | TwoFactorPayload,
  ): Observable<User> {
    return this.http.post<ApiEnvelope<AuthResult>>(url, body).pipe(
      map((response) => response.data),
      tap((result) => {
        this.tokenSignal.set(result.token);
        this.userSignal.set(result.user);
        this.persist();
      }),
      map((result) => result.user),
    );
  }

  /**
   * Réhydrate la session depuis le `sessionStorage` (navigateur uniquement), de
   * façon **synchrone** pour que les guards voient l'état dès le premier rendu
   * client, puis revalide le jeton en arrière-plan.
   */
  private restoreSession(): void {
    if (!this.isBrowser) {
      return; // pas de sessionStorage au rendu serveur (SSR)
    }

    const raw = sessionStorage.getItem(SESSION_KEY);
    if (!raw) {
      return;
    }

    try {
      const saved = JSON.parse(raw) as { token: string; user: User };
      if (saved?.token) {
        this.tokenSignal.set(saved.token);
        this.userSignal.set(saved.user ?? null);
        this.revalidate();
      }
    } catch {
      sessionStorage.removeItem(SESSION_KEY); // entrée corrompue
    }
  }

  /**
   * Revalide le jeton restauré via `GET /users/me` et rafraîchit l'utilisateur.
   * Un jeton révoqué renvoie 401 → l'`errorInterceptor` appelle `clearSession()`.
   */
  private revalidate(): void {
    this.http.get<ApiEnvelope<{ user: User }>>(`${this.api}/users/me`).subscribe({
      next: (response) => this.setCurrentUser(response.data.user),
      error: () => void 0, // 401 déjà traité par l'errorInterceptor
    });
  }

  /** Écrit la session courante dans le `sessionStorage` (navigateur uniquement). */
  private persist(): void {
    if (!this.isBrowser) {
      return;
    }
    const token = this.tokenSignal();
    const user = this.userSignal();
    if (token) {
      sessionStorage.setItem(SESSION_KEY, JSON.stringify({ token, user }));
    }
  }
}
