import { registerLocaleData } from '@angular/common';
import localeFr from '@angular/common/locales/fr';
import {
  ApplicationConfig,
  provideBrowserGlobalErrorListeners,
  provideEnvironmentInitializer,
} from '@angular/core';
import { provideHttpClient, withFetch, withInterceptors } from '@angular/common/http';
import { provideClientHydration, withEventReplay } from '@angular/platform-browser';
import { provideRouter, withInMemoryScrolling } from '@angular/router';

import { routes } from './app.routes';
import { activerPolitiqueDeDefilement } from './core/scroll/scroll-behavior';
import { errorInterceptor } from './core/interceptors/error.interceptor';
import { tokenInterceptor } from './core/interceptors/token.interceptor';

// Rend disponibles les données de locale française (noms de mois/jours, formats
// de date). On n'impose PAS `LOCALE_ID: 'fr'` globalement — pour ne pas modifier
// le formatage des nombres ailleurs — mais on peut passer `'fr'` en paramètre
// des pipes (ex. `date: 'longDate' : '' : 'fr'` sur l'écran profil, F3.2).
registerLocaleData(localeFr);

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    // Défilement : politique MAISON (F8.20), voir `core/scroll/scroll-behavior`.
    // ⚠️ `scrollPositionRestoration: 'disabled'` ne veut pas dire « aucun
    // défilement » : le routeur continue d'émettre ses événements `Scroll` (avec
    // la position mémorisée et l'ancre), c'est nous qui décidons quoi en faire.
    // La politique intégrée remontait en haut à CHAQUE navigation, y compris sur
    // un simple changement de filtre — les filtres vivant dans l'URL.
    provideRouter(
      routes,
      withInMemoryScrolling({ scrollPositionRestoration: 'disabled', anchorScrolling: 'disabled' }),
    ),
    provideEnvironmentInitializer(activerPolitiqueDeDefilement),
    // Client HTTP + interceptors fonctionnels : on ajoute d'abord le jeton
    // (tokenInterceptor), puis on traite les erreurs de la réponse (errorInterceptor).
    // `withFetch()` : au rendu serveur (SSR, F2.9) le HttpClient s'appuie sur
    // l'API fetch de Node plutôt que sur un XHR simulé — recommandé pour le SSR.
    provideHttpClient(withInterceptors([tokenInterceptor, errorInterceptor]), withFetch()),
    // Hydratation (F2.9) : le client reprend le DOM rendu par le serveur sans le
    // reconstruire. Le « transfer cache » HTTP est actif par défaut (les GET
    // faits pendant le rendu serveur sont réutilisés côté client, pas rejoués).
    // `withEventReplay()` : les clics survenus avant l'hydratation sont rejoués.
    provideClientHydration(withEventReplay()),
  ],
};
