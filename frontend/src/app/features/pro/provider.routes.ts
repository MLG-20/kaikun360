import { Routes } from '@angular/router';

import { roleGuard } from '../../core/guards/role.guard';
import { SpaceLayoutComponent } from '../../layouts/space-layout/space-layout';
import { SPACE_CONFIG } from '../../layouts/space-layout/space.config';
import { PROVIDER_SPACE } from './provider-space';

/**
 * Routes de l'**espace prestataire** (F5), montées sous `/espace-prestataire`.
 *
 * Réutilise le **shell générique** `SpaceLayoutComponent`, paramétré par
 * `PROVIDER_SPACE` (fourni via `SPACE_CONFIG`). Toute la branche est protégée
 * par `roleGuard` avec le rôle `prestataire` : sans session → redirection
 * connexion (avec `redirect`) ; connecté sans le rôle → renvoi vers son propre
 * espace.
 *
 * Les rubriques se branchent au fil des sous-phases : tableau de bord (F5.1) est
 * en place ; Mes services, Disponibilités (F5.4), Missions (F5.2), Avis (F5.5)
 * et Revenus (F5.3) suivront.
 */
export const PROVIDER_ROUTES: Routes = [
  {
    path: '',
    component: SpaceLayoutComponent,
    canActivate: [roleGuard],
    data: { roles: ['prestataire'] },
    providers: [{ provide: SPACE_CONFIG, useValue: PROVIDER_SPACE }],
    children: [
      {
        // F5.1 — Tableau de bord : profil prestataire + statut (GET /providers/mine).
        path: '',
        loadComponent: () =>
          import('./overview/provider-overview-page').then(
            (m) => m.ProviderOverviewPageComponent,
          ),
        title: 'Espace prestataire — Kaikun 360',
      },
      {
        // F5.2 — Missions reçues (GET /provider-missions/mine + transitions).
        path: 'missions',
        loadComponent: () =>
          import('./missions/provider-missions-page').then(
            (m) => m.ProviderMissionsPageComponent,
          ),
        title: 'Missions reçues — Kaikun 360',
      },
      {
        // F5.3 — Revenus & commissions (GET /provider-missions/earnings).
        path: 'revenus',
        loadComponent: () =>
          import('./earnings/provider-earnings-page').then(
            (m) => m.ProviderEarningsPageComponent,
          ),
        title: 'Revenus & commissions — Kaikun 360',
      },
      {
        // Profil — écran transverse monté DANS l'espace prestataire (réutilise le
        // composant de l'espace client : il porte sur l'utilisateur connecté).
        path: 'profil',
        loadComponent: () =>
          import('../account/profile/profile-page').then((m) => m.ProfilePageComponent),
        title: 'Mon profil — Kaikun 360',
      },
      {
        // Notifications — même principe que le profil (écran transverse).
        path: 'notifications',
        loadComponent: () =>
          import('../account/notifications/notifications-page').then(
            (m) => m.NotificationsPageComponent,
          ),
        title: 'Mes notifications — Kaikun 360',
      },
    ],
  },
];
