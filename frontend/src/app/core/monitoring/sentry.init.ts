import { isPlatformBrowser } from '@angular/common';
import { PLATFORM_ID, inject } from '@angular/core';
import * as Sentry from '@sentry/angular';

import { environment } from '../../../environments/environment';

/**
 * **Suivi des erreurs Sentry** (monitoring, 2026-08-28).
 *
 * Sur le modèle de `activerLaMesureDAudience` (`core/analytics/`), mais sans
 * bandeau de consentement à attendre : contrairement à GA4, Sentry ne suit
 * pas les visiteurs, il capture des erreurs techniques. `send_default_pii`
 * est laissé à `false` côté SDK, aucune donnée personnelle n'est jointe.
 *
 * ⚠️ **Ne s'active QUE si `environment.sentryDsn` est renseigné** — vide en
 * développement et en démo (voir `environment.development.ts` /
 * `environment.demo.ts`), pour ne pas polluer le projet de production avec
 * des erreurs locales.
 *
 * ⚠️ **Jamais au rendu serveur (SSR)** : ce fichier initialise le SDK
 * navigateur (`@sentry/angular`) ; les erreurs PHP côté serveur sont déjà
 * couvertes indépendamment par `sentry/sentry-laravel` sur le backend.
 */
export function activerLeSuiviDesErreurs(): void {
  const isBrowser = isPlatformBrowser(inject(PLATFORM_ID));

  if (!isBrowser || environment.sentryDsn.trim().length === 0) {
    return;
  }

  Sentry.init({
    dsn: environment.sentryDsn,
    environment: environment.production ? 'production' : 'development',
    // Pas de traçage de performance ni de replay pour l'instant : on démarre
    // avec la seule capture d'erreurs, la plus utile et la moins coûteuse en
    // quota (plan gratuit Sentry).
    tracesSampleRate: 0,
  });
}
