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
 * Les rubriques se branchent au fil des sous-phases : tableau de bord (F4.1),
 * Mes biens (F4.2, liste + fiche) et dépôt/édition d'un bien (F4.3, formulaire +
 * mode de location) sont en place ; Gestion locative (F4.4) et Documents (F4.5)
 * suivront.
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
        // F4.3 — Dépôt d'un bien (POST /properties + PUT config nuitées).
        // ⚠️ Déclaré AVANT `biens/:id` sinon « nouveau » serait pris pour un id.
        path: 'biens/nouveau',
        loadComponent: () =>
          import('./properties/owner-property-form-page').then(
            (m) => m.OwnerPropertyFormPageComponent,
          ),
        title: 'Déposer un bien — Kaikun 360',
      },
      {
        // F4.3 — Édition d'un bien (préremplissage + PATCH /properties + nuitées).
        path: 'biens/:id/modifier',
        loadComponent: () =>
          import('./properties/owner-property-form-page').then(
            (m) => m.OwnerPropertyFormPageComponent,
          ),
        title: 'Modifier le bien — Kaikun 360',
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
      {
        // F4.4 — Gestion locative : liste des mandats (GET /manage/mandates/mine).
        path: 'gestion-locative',
        loadComponent: () =>
          import('./manage/owner-manage-page').then((m) => m.OwnerManagePageComponent),
        title: 'Gestion locative — Kaikun 360',
      },
      {
        // F4.4 — Fiche d'un mandat + rapport mensuel (GET /manage/mandates/{id}).
        path: 'gestion-locative/:id',
        loadComponent: () =>
          import('./manage/owner-mandate-detail-page').then(
            (m) => m.OwnerMandateDetailPageComponent,
          ),
        title: 'Mandat de gestion — Kaikun 360',
      },
      {
        // Profil — écrans transverses montés DANS l'espace propriétaire.
        // On réutilise les composants de l'espace client (ils portent sur
        // l'utilisateur connecté, pas sur un espace), mais sous le shell
        // propriétaire : cliquer « Mon profil » ne doit JAMAIS éjecter le
        // propriétaire vers `/mon-espace` (chaque espace est autonome).
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
