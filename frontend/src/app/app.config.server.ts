import { mergeApplicationConfig, ApplicationConfig } from '@angular/core';
import { provideServerRendering, withRoutes } from '@angular/ssr';
import { appConfig } from './app.config';
import { serverRoutes } from './app.routes.server';
import { API_ORIGIN } from './core/interceptors/server-api-origin.interceptor';

const serverConfig: ApplicationConfig = {
  providers: [
    provideServerRendering(withRoutes(serverRoutes)),
    // --- Origine de l'API vue depuis le processus Node (F9.1) --------------
    //
    // ⚠️ **`process.env` est lu ICI et nulle part ailleurs** : ce fichier n'est
    // inclus que dans le paquet serveur. Y toucher depuis un fichier partagé
    // ferait échouer la construction du paquet navigateur, où `process`
    // n'existe pas.
    //
    // ⚠️ Ce réglage ne remplace pas `environment.apiUrl` : il le complète. En
    // production celui-ci vaut `/api/v1`, une adresse relative qui ne se résout
    // que dans un navigateur. Sans cette origine, le rendu serveur appelait sa
    // PROPRE adresse (le port 4000), recevait du HTML au lieu du JSON, et
    // rendait « introuvable » chaque fiche du catalogue — invisible à l'écran,
    // mais c'est exactement ce que lisent Google et l'aperçu WhatsApp.
    //
    // Même variable que le relais du plan du site dans `server.ts` : une seule
    // valeur à renseigner au déploiement.
    {
      provide: API_ORIGIN,
      useValue: (process.env['API_ORIGIN'] ?? 'http://localhost:8000').replace(/\/+$/, ''),
    },
  ],
};

export const config = mergeApplicationConfig(appConfig, serverConfig);
