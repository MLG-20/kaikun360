import { Routes } from '@angular/router';

import { MainLayoutComponent } from './layouts/main-layout/main-layout';

/**
 * Routeur racine (F1.1).
 *
 * Deux branches de layout :
 *   - `/auth/*` → layout d'authentification (chargé à la demande) ;
 *   - le reste → layout principal (en-tête/pied), avec l'accueil pour l'instant.
 *
 * `auth` est déclaré AVANT la branche `''` (qui matche par préfixe) pour être
 * résolu en priorité.
 */
export const routes: Routes = [
  {
    path: 'auth',
    loadChildren: () => import('./features/auth/auth.routes').then((m) => m.AUTH_ROUTES),
  },
  {
    path: '',
    component: MainLayoutComponent,
    children: [
      {
        path: '',
        loadComponent: () => import('./features/home/home-page').then((m) => m.HomePageComponent),
        title: 'Kaikun 360 — Immobilier, tourisme & services au Sénégal',
      },
      {
        // Page de résultats du moteur de recherche (F2.1). L'univers et les
        // filtres sont portés par les query params (ex. /recherche?univers=nuitees).
        path: 'recherche',
        loadComponent: () =>
          import('./features/catalog/catalog-page').then((m) => m.CatalogPageComponent),
        title: 'Recherche — Kaikun 360',
      },
    ],
  },
];
