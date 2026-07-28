import { Routes } from '@angular/router';

import { roleGuard } from '../../core/guards/role.guard';
import { BackofficeLayoutComponent } from '../../layouts/backoffice-layout/backoffice-layout';

/**
 * Routes du **back-office** (F7.1.e) — poste de commandement de l'équipe.
 *
 * Montées sous `/back-office`, toutes protégées par `roleGuard` avec les rôles
 * de l'équipe (agent / admin / super_admin). Un compte sans l'un de ces rôles
 * est renvoyé vers son propre espace (comportement du guard). Le shell est
 * **dédié** (`BackofficeLayoutComponent`), distinct de celui des espaces.
 *
 * F7.1.e ne livre que la « Vue d'ensemble » ; les rubriques Équipe, Permissions
 * et Pointeuse (routes à venir) s'ajouteront ici comme enfants.
 */
export const BACKOFFICE_ROUTES: Routes = [
  {
    path: '',
    component: BackofficeLayoutComponent,
    canActivate: [roleGuard],
    data: { roles: ['agent_kaikun', 'admin', 'super_admin'] },
    children: [
      {
        path: '',
        loadComponent: () =>
          import('./overview/backoffice-overview-page').then(
            (m) => m.BackofficeOverviewPageComponent,
          ),
        title: 'Back-office — Kaikun 360',
      },
      {
        // F7.2.a — Validation : file d'approbation des ressources (biens,
        // véhicules, expériences, prestataires) + décision valider/refuser.
        path: 'validation',
        loadComponent: () =>
          import('./validation/backoffice-validation-page').then(
            (m) => m.BackofficeValidationPageComponent,
          ),
        title: 'Validation — Back-office Kaikun 360',
      },
      {
        // F7.2.b — Catalogues : navigateur de supervision (biens / véhicules /
        // expériences), tous statuts, en lecture seule.
        path: 'catalogues',
        loadComponent: () =>
          import('./catalogues/backoffice-catalogues-page').then(
            (m) => m.BackofficeCataloguesPageComponent,
          ),
        title: 'Catalogues — Back-office Kaikun 360',
      },
      {
        // F7.2.c — Nuitées : calendrier des séjours + check-in/out + ménage.
        path: 'nuitees',
        loadComponent: () =>
          import('./stays/backoffice-stays-page').then((m) => m.BackofficeStaysPageComponent),
        title: 'Nuitées — Back-office Kaikun 360',
      },
      {
        // F7.2.d — Paiements : supervision + confirmation manuelle Wave/OM + remboursement.
        path: 'paiements',
        loadComponent: () =>
          import('./payments/backoffice-payments-page').then(
            (m) => m.BackofficePaymentsPageComponent,
          ),
        title: 'Paiements — Back-office Kaikun 360',
      },
      {
        // F7.2.e — Dossiers : supervision construction + mandats de gestion (lecture seule).
        path: 'dossiers',
        loadComponent: () =>
          import('./dossiers/backoffice-dossiers-page').then(
            (m) => m.BackofficeDossiersPageComponent,
          ),
        title: 'Dossiers — Back-office Kaikun 360',
      },
      {
        // F7.1.f — Équipe : annuaire, enrôlement, pilotage rôle/statut.
        path: 'equipe',
        loadComponent: () =>
          import('./team/backoffice-team-page').then((m) => m.BackofficeTeamPageComponent),
        title: 'Équipe — Back-office Kaikun 360',
      },
      {
        // F7.1.g — Permissions : matrice de délégation des dossiers par agent.
        path: 'permissions',
        loadComponent: () =>
          import('./permissions/backoffice-permissions-page').then(
            (m) => m.BackofficePermissionsPageComponent,
          ),
        title: 'Permissions — Back-office Kaikun 360',
      },
      {
        // F7.1.h — Pointeuse : présences (perso) + feuille d'équipe + export.
        path: 'pointeuse',
        loadComponent: () =>
          import('./attendance/backoffice-attendance-page').then(
            (m) => m.BackofficeAttendancePageComponent,
          ),
        title: 'Pointeuse — Back-office Kaikun 360',
      },
    ],
  },
];
