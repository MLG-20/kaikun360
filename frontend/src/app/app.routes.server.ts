import { RenderMode, ServerRoute } from '@angular/ssr';

/**
 * Stratégie de rendu serveur (F2.9).
 *
 * Toutes les pages publiques sont rendues **côté serveur à la demande**
 * (`RenderMode.Server`), pas prérendues au build. Raisons :
 *   - beaucoup de pages sont dynamiques (`/immobilier/:id`, `/nuitees/:id`,
 *     `/pages/:slug`, `/recherche?...`) : leurs paramètres ne sont pas
 *     énumérables au build, donc le prérendu (`Prerender`) échouerait ou
 *     produirait une page figée ;
 *   - les pages vitrine s'alimentent auprès du backend Laravel : les prérendre
 *     coupleraient le build à une API joignable et gèlerait des données au
 *     moment de la compilation. Le rendu à la demande sert toujours des données
 *     fraîches et un HTML complet aux robots (SEO), puis le client hydrate.
 *
 * Le serveur ne connaît jamais la session (le jeton est restauré côté client
 * depuis le `sessionStorage`, indisponible au SSR). Il rend donc toujours la vue
 * « visiteur non connecté », ce qui est exactement ce qu'un moteur d'indexation
 * doit voir sur des pages publiques.
 *
 * ⚠️ Conséquence pour les **espaces privés** (`mon-espace`, `espace-proprietaire`,
 * `espace-prestataire`) : les rendre au serveur y ferait tourner les guards de
 * rôle SANS session → une **redirection systématique vers la connexion**, y
 * compris lors d'un simple rafraîchissement de page. On les bascule donc en
 * **`RenderMode.Client`** : le serveur envoie la coquille, puis le client
 * restaure la session (sessionStorage) et exécute les guards LÀ où le jeton
 * existe. Ces pages n'ont de toute façon aucun intérêt SEO (contenu privé).
 */
export const serverRoutes: ServerRoute[] = [
  { path: 'mon-espace', renderMode: RenderMode.Client },
  { path: 'mon-espace/**', renderMode: RenderMode.Client },
  { path: 'espace-proprietaire', renderMode: RenderMode.Client },
  { path: 'espace-proprietaire/**', renderMode: RenderMode.Client },
  { path: 'espace-prestataire', renderMode: RenderMode.Client },
  { path: 'espace-prestataire/**', renderMode: RenderMode.Client },
  {
    path: '**',
    renderMode: RenderMode.Server,
  },
];
