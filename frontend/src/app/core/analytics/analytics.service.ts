import { isPlatformBrowser } from '@angular/common';
import { Injectable, PLATFORM_ID, effect, inject } from '@angular/core';
import { NavigationEnd, Router } from '@angular/router';
import { filter } from 'rxjs/operators';

import { environment } from '../../../environments/environment';
import { CookieConsentService } from './cookie-consent.service';

/** Sous-ensemble typé de `gtag.js` que nous utilisons. */
type Gtag = (...args: unknown[]) => void;

declare global {
  interface Window {
    dataLayer?: unknown[];
    gtag?: Gtag;
  }
}

/**
 * **Mesure d'audience Google Analytics 4** (F16, 2026-08-20).
 *
 * Sur le modèle de `GoogleIdentityService` (`core/auth/`) : le script tiers
 * (`gtag.js`) n'est injecté qu'à la demande, jamais au rendu serveur.
 *
 * ⚠️ **Trois conditions cumulatives**, sinon rien ne se charge :
 *   1. `environment.gaMeasurementId` non vide (vide en développement/démo,
 *      voir `environment.development.ts`) ;
 *   2. `CookieConsentService.estAccepte()` vrai — le bandeau cookies a été
 *      accepté. C'est la promesse faite par `/pages/politique-cookies` :
 *      « activé qu'après votre consentement, recueilli par un bandeau dédié » ;
 *   3. on est dans le navigateur (pas de `window`/`document` au SSR).
 *
 * ⚠️ **`send_page_view: false` à l'initialisation.** GA4 envoie par défaut
 * UNE vue de page au chargement du script — insuffisant pour une SPA où la
 * plupart des navigations ne rechargent jamais la page. On désactive cet
 * envoi automatique et on émet nous-mêmes un événement `page_view` à chaque
 * `NavigationEnd`, `page_location`/`page_path` recalculés à chaque fois.
 *
 * ⚠️ **Un refus est définitif pour la session, pas seulement pour la visite
 * courante** : une fois `gtag.js` chargé, il n'existe aucun moyen fiable de
 * le « décharger ». Si le visiteur accepte APRÈS avoir navigué, on charge le
 * script et on envoie une vue pour la page où il se trouve à cet instant —
 * les pages vues avant son accord ne sont, à raison, jamais remontées.
 */
@Injectable({ providedIn: 'root' })
export class AnalyticsService {
  private readonly isBrowser = isPlatformBrowser(inject(PLATFORM_ID));
  private readonly mesureId = environment.gaMeasurementId;
  private readonly consentement = inject(CookieConsentService);
  private readonly router = inject(Router);

  private scriptCharge = false;

  /** Est-ce que la mesure d'audience est configurée pour cet environnement ? */
  private get estConfiguree(): boolean {
    return this.isBrowser && this.mesureId.trim().length > 0;
  }

  /**
   * Démarre le service : à appeler une seule fois, depuis
   * `provideEnvironmentInitializer` (voir `app.config.ts`).
   */
  demarrer(): void {
    if (!this.estConfiguree) {
      return;
    }

    // Charge (ou décharge, au sens de : cesse d'envoyer) selon le consentement.
    effect(() => {
      if (this.consentement.estAccepte()) {
        this.chargerEtEnvoyerLaPageCourante();
      }
    });

    // Une navigation dans la SPA ne recharge jamais `gtag.js` : c'est nous qui
    // signalons chaque changement de page, et seulement si déjà consenti.
    this.router.events.pipe(filter((event) => event instanceof NavigationEnd)).subscribe(() => {
      if (this.consentement.estAccepte()) {
        this.envoyerLaPageCourante();
      }
    });
  }

  private chargerEtEnvoyerLaPageCourante(): void {
    if (this.scriptCharge) {
      this.envoyerLaPageCourante();
      return;
    }

    window.dataLayer = window.dataLayer ?? [];
    // `gtag` doit exister AVANT que le script distant charge : il empile ses
    // appels dans `dataLayer`, que `gtag.js` rejoue une fois prêt.
    window.gtag = function gtag(...args: unknown[]) {
      window.dataLayer?.push(args);
    };
    window.gtag('js', new Date());
    window.gtag('config', this.mesureId, { send_page_view: false });

    const script = document.createElement('script');
    script.src = `https://www.googletagmanager.com/gtag/js?id=${this.mesureId}`;
    script.async = true;
    script.onload = () => {
      this.scriptCharge = true;
      this.envoyerLaPageCourante();
    };
    document.head.appendChild(script);
  }

  private envoyerLaPageCourante(): void {
    window.gtag?.('event', 'page_view', {
      page_location: window.location.href,
      page_path: this.router.url,
      page_title: document.title,
    });
  }
}

/**
 * Fonction d'amorçage pour `provideEnvironmentInitializer` (sur le modèle de
 * `activerPolitiqueDeDefilement`, `core/scroll/scroll-behavior.ts`).
 */
export function activerLaMesureDAudience(): void {
  inject(AnalyticsService).demarrer();
}
