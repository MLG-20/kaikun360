import { Routes } from '@angular/router';

import { roleGuard } from '../../core/guards/role.guard';
import { SpaceLayoutComponent } from '../../layouts/space-layout/space-layout';
import { SPACE_CONFIG } from '../../layouts/space-layout/space.config';
import { OWNER_SPACE } from './owner-space';

/**
 * Routes de l'**espace propriétaire** (F4), montées sous `/espace-proprietaire`.
 *
 * Réutilise le **shell générique** `SpaceLayoutComponent`, paramétré par
 * `OWNER_SPACE` (fourni via `SPACE_CONFIG`). Toute la branche est protégée par
 * `roleGuard` avec le rôle `proprietaire` : sans session → redirection connexion
 * (avec `redirect`) ; connecté sans le rôle → renvoi à l'accueil.
 *
 * Les rubriques (Mes biens F4.2/F4.3, Gestion locative F4.4, Documents F4.5) se
 * brancheront ici au fil des sous-phases ; seul le tableau de bord existe en
 * F4.1.
 */
export const OWNER_ROUTES: Routes = [
  {
    path: '',
    component: SpaceLayoutComponent,
    canActivate: [roleGuard],
    data: { roles: ['proprietaire'] },
    providers: [{ provide: SPACE_CONFIG, useValue: OWNER_SPACE }],
    children: [
      {
        // F4.1 — Tableau de bord : agrégats de gestion locative (GET /manage/dashboard).
        path: '',
        loadComponent: () =>
          import('./overview/owner-overview-page').then((m) => m.OwnerOverviewPageComponent),
        title: 'Espace propriétaire — Kaikun 360',
      },
    ],
  },
];
