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
 * Les rubriques se branchent au fil des sous-phases : tableau de bord (F4.1) et
 * Mes biens (F4.2, liste + fiche) sont en place ; Gestion locative (F4.4) et
 * Documents (F4.5) suivront. Le dépôt/édition d'un bien viendra en F4.3.
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
      {
        // F4.2 — Mes biens : liste + statut de validation (GET /properties/mine).
        path: 'biens',
        loadComponent: () =>
          import('./properties/owner-properties-page').then((m) => m.OwnerPropertiesPageComponent),
        title: 'Mes biens — Kaikun 360',
      },
      {
        // F4.2 — Fiche d'un bien (GET /properties/mine/{id}, propriétaire seul).
        path: 'biens/:id',
        loadComponent: () =>
          import('./properties/owner-property-detail-page').then(
            (m) => m.OwnerPropertyDetailPageComponent,
          ),
        title: 'Mon bien — Kaikun 360',
      },
    ],
  },
];
