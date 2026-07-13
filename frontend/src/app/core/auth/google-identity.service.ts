import { Injectable } from '@angular/core';

import { environment } from '../../../environments/environment';

/**
 * Réponse de Google Identity Services après une connexion réussie : elle contient
 * le « jeton d'identité » (`credential`) que le backend vérifiera.
 */
interface GoogleCredentialResponse {
  credential: string;
}

/**
 * Sous-ensemble typé de l'API `google.accounts.id` que nous utilisons (la
 * librairie Google n'a pas de types fournis ; on déclare le strict nécessaire).
 */
interface GoogleAccountsId {
  initialize(config: {
    client_id: string;
    callback: (response: GoogleCredentialResponse) => void;
  }): void;
  renderButton(parent: HTMLElement, options: Record<string, unknown>): void;
}

declare global {
  interface Window {
    google?: { accounts: { id: GoogleAccountsId } };
  }
}

/** URL officielle de la librairie Google Identity Services. */
const GSI_SRC = 'https://accounts.google.com/gsi/client';

/**
 * Intégration du bouton « Connexion Google » (F1.4).
 *
 * Ce service charge la librairie officielle de Google **à la demande** (seulement
 * si un identifiant client est configuré), affiche le bouton Google dans un
 * emplacement donné, et remonte le jeton d'identité obtenu — que l'appelant
 * transmet ensuite au backend via `AuthService.loginWithGoogle`.
 *
 * Tant que `environment.googleClientId` est vide (identifiant non fourni par le
 * client), `isEnabled` vaut faux et rien n'est chargé : le bouton n'apparaît pas.
 */
@Injectable({ providedIn: 'root' })
export class GoogleIdentityService {
  private readonly clientId = environment.googleClientId;
  private scriptPromise: Promise<void> | null = null;

  /** La connexion Google est-elle disponible (identifiant client configuré) ? */
  get isEnabled(): boolean {
    return this.clientId.trim().length > 0;
  }

  /**
   * Affiche le bouton Google dans l'élément fourni. `onToken` est appelé avec le
   * jeton d'identité quand l'utilisateur s'est connecté côté Google.
   */
  async renderButton(parent: HTMLElement, onToken: (idToken: string) => void): Promise<void> {
    if (!this.isEnabled) {
      return;
    }

    await this.loadScript();

    const accounts = window.google?.accounts.id;
    if (!accounts) {
      return;
    }

    accounts.initialize({
      client_id: this.clientId,
      callback: (response) => onToken(response.credential),
    });

    accounts.renderButton(parent, {
      type: 'standard',
      theme: 'outline',
      size: 'large',
      text: 'continue_with',
      shape: 'pill',
      width: parent.clientWidth || 320,
      locale: 'fr',
    });
  }

  /** Injecte la librairie Google une seule fois (mémoïsée). */
  private loadScript(): Promise<void> {
    if (this.scriptPromise) {
      return this.scriptPromise;
    }

    this.scriptPromise = new Promise<void>((resolve, reject) => {
      // Déjà présente (ex. seconde visite de la page) : rien à charger.
      if (window.google?.accounts?.id) {
        resolve();
        return;
      }

      const script = document.createElement('script');
      script.src = GSI_SRC;
      script.async = true;
      script.defer = true;
      script.onload = () => resolve();
      script.onerror = () => reject(new Error('Chargement de Google Identity Services impossible.'));
      document.head.appendChild(script);
    });

    return this.scriptPromise;
  }
}
