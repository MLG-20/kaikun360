import { ApplicationConfig, provideBrowserGlobalErrorListeners } from '@angular/core';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { provideRouter } from '@angular/router';

import { routes } from './app.routes';
import { errorInterceptor } from './core/interceptors/error.interceptor';
import { tokenInterceptor } from './core/interceptors/token.interceptor';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideRouter(routes),
    // Client HTTP + interceptors fonctionnels : on ajoute d'abord le jeton
    // (tokenInterceptor), puis on traite les erreurs de la réponse (errorInterceptor).
    provideHttpClient(withInterceptors([tokenInterceptor, errorInterceptor])),
  ],
};
