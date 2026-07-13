import { ApplicationConfig, provideBrowserGlobalErrorListeners } from '@angular/core';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { provideRouter, withInMemoryScrolling } from '@angular/router';

import { routes } from './app.routes';
import { errorInterceptor } from './core/interceptors/error.interceptor';
import { tokenInterceptor } from './core/interceptors/token.interceptor';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    // Défilement : on remonte en haut à chaque changement de page et on gère les
    // liens à ancre (#section) — utilisés par les tuiles d'univers de l'accueil
    // qui pointent vers des sections plus bas dans la même page.
    provideRouter(
      routes,
      withInMemoryScrolling({ scrollPositionRestoration: 'enabled', anchorScrolling: 'enabled' }),
    ),
    // Client HTTP + interceptors fonctionnels : on ajoute d'abord le jeton
    // (tokenInterceptor), puis on traite les erreurs de la réponse (errorInterceptor).
    provideHttpClient(withInterceptors([tokenInterceptor, errorInterceptor])),
  ],
};
