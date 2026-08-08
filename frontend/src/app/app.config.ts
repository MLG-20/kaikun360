import { registerLocaleData } from '@angular/common';
import localeFr from '@angular/common/locales/fr';
import {
  ApplicationConfig,
  isDevMode,
  provideBrowserGlobalErrorListeners,
  provideEnvironmentInitializer,
} from '@angular/core';
import { provideServiceWorker } from '@angular/service-worker';
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
    // --- PWA (F9.0) : l'application devient installable ---------------------
    //
    // Réponse au CDC §5 (« application mobile »), par la voie décidée avec le
    // client : une **PWA installable** plutôt qu'un projet Expo. L'API, les
    // écrans et les 4 espaces existent déjà ; ce qui manquait, c'était l'icône
    // sur l'écran d'accueil et la tenue sur une connexion faible.
    //
    // ⚠️ **`isDevMode()` et pas seulement `!isDevMode()` par confort** : un
    // service worker actif en développement sert des bundles mis en cache et
    // fait « mentir » le rechargement à chaud — on croit corriger un fichier
    // que le navigateur ne relit jamais.
    //
    // ⚠️ **`registerWhenStable:30000`** : l'enregistrement attend que
    // l'application soit stable (30 s au plus). Sur une connexion sénégalaise
    // moyenne, enregistrer tout de suite ferait concourir le préchargement du
    // service worker avec l'affichage de la première page — exactement le
    // contraire du but recherché.
    //
    // ⚠️ **Aucune donnée personnelle n'est mise en cache**, et c'est une règle
    // de sécurité, pas un réglage : `ngsw-config.json` n'énumère QUE des
    // endpoints publics (catalogues, pages, référentiel géo). Un cache de
    // service worker vit **par origine, pas par utilisateur** — y laisser entrer
    // `/users/me`, `/bookings` ou `/messages` rendrait les données d'un compte
    // lisibles par le suivant sur un téléphone partagé.
    provideServiceWorker('ngsw-worker.js', {
      enabled: !isDevMode(),
      registrationStrategy: 'registerWhenStable:30000',
    }),
  ],
};
