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
      {
        // Univers Immobilier (F2.3) : page vitrine + fiche détaillée d'un bien.
        path: 'immobilier',
        loadComponent: () =>
          import('./features/immo/property-list/property-list-page').then(
            (m) => m.PropertyListPageComponent,
          ),
        title: 'Immobilier vérifié — Kaikun 360',
      },
      {
        path: 'immobilier/:id',
        loadComponent: () =>
          import('./features/immo/property-detail/property-detail-page').then(
            (m) => m.PropertyDetailPageComponent,
          ),
        title: 'Bien immobilier — Kaikun 360',
      },
      {
        // Univers Nuitées (F2.3) : page vitrine + fiche détaillée d'une nuitée.
        path: 'nuitees',
        loadComponent: () =>
          import('./features/stay/stay-list/stay-list-page').then(
            (m) => m.StayListPageComponent,
          ),
        title: 'Nuitées & séjours — Kaikun 360',
      },
      {
        path: 'nuitees/:id',
        loadComponent: () =>
          import('./features/stay/stay-detail/stay-detail-page').then(
            (m) => m.StayDetailPageComponent,
          ),
        title: 'Nuitée — Kaikun 360',
      },
      {
        // Univers Tourisme (F2.4) : page vitrine + fiche détaillée d'une expérience.
        path: 'tourisme',
        loadComponent: () =>
          import('./features/explore/experience-list/experience-list-page').then(
            (m) => m.ExperienceListPageComponent,
          ),
        title: 'Tourisme & expériences — Kaikun 360',
      },
      {
        path: 'tourisme/:id',
        loadComponent: () =>
          import('./features/explore/experience-detail/experience-detail-page').then(
            (m) => m.ExperienceDetailPageComponent,
          ),
        title: 'Expérience — Kaikun 360',
      },
      {
        // Univers Transport (F2.4) : page vitrine + fiche détaillée d'un véhicule.
        path: 'transport',
        loadComponent: () =>
          import('./features/mobility/vehicle-list/vehicle-list-page').then(
            (m) => m.VehicleListPageComponent,
          ),
        title: 'Transport & location — Kaikun 360',
      },
      {
        path: 'transport/:id',
        loadComponent: () =>
          import('./features/mobility/vehicle-detail/vehicle-detail-page').then(
            (m) => m.VehicleDetailPageComponent,
          ),
        title: 'Véhicule — Kaikun 360',
      },
      {
        // Univers Mobilité (F2.4) : vitrine seule (pas de fiche côté backend).
        path: 'mobilite',
        loadComponent: () =>
          import('./features/mobility/mobility-list/mobility-list-page').then(
            (m) => m.MobilityListPageComponent,
          ),
        title: 'Mobilité, navettes & transferts — Kaikun 360',
      },
      {
        // Univers Construction (F2.5) : page de conversion + simulateur de budget.
        path: 'construction',
        loadComponent: () =>
          import('./features/build/construction-page/construction-page').then(
            (m) => m.ConstructionPageComponent,
          ),
        title: 'Construction & simulateur de budget — Kaikun 360',
      },
      {
        // Univers Gestion locative (F2.5) : page de conversion.
        path: 'gestion-locative',
        loadComponent: () =>
          import('./features/manage/manage-page/manage-page').then(
            (m) => m.ManagePageComponent,
          ),
        title: 'Gestion locative — Kaikun 360',
      },
      {
        // Univers Diaspora (F2.5) : page de conversion (protocole de confiance).
        path: 'diaspora',
        loadComponent: () =>
          import('./features/diaspora/diaspora-page/diaspora-page').then(
            (m) => m.DiasporaPageComponent,
          ),
        title: 'Diaspora — projets pilotés à distance — Kaikun 360',
      },
      {
        // Univers Team building (F2.5) : page de conversion.
        path: 'team-building',
        loadComponent: () =>
          import('./features/team-building/team-building-page/team-building-page').then(
            (m) => m.TeamBuildingPageComponent,
          ),
        title: 'Team building & séminaires — Kaikun 360',
      },
      {
        // Kaikun Pro (F2.5) : page de conversion prestataires/entreprises.
        path: 'pro',
        loadComponent: () =>
          import('./features/pro/pro-page/pro-page').then((m) => m.ProPageComponent),
        title: 'Kaikun Pro — devenez prestataire vérifié — Kaikun 360',
      },
    ],
  },
];
