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
 * Le jeton de session vit uniquement en mémoire (voir AuthService) : le serveur
 * rend donc toujours la vue « visiteur non connecté », ce qui est exactement ce
 * qu'un moteur d'indexation doit voir sur des pages publiques.
 */
export const serverRoutes: ServerRoute[] = [
  {
    path: '**',
    renderMode: RenderMode.Server,
  },
];
