import { Routes } from '@angular/router';

import { roleGuard } from '../../core/guards/role.guard';
import { SpaceLayoutComponent } from '../../layouts/space-layout/space-layout';
import { SPACE_CONFIG } from '../../layouts/space-layout/space.config';
import { ENTERPRISE_SPACE } from './enterprise-space';

/**
 * Routes de l'**espace entreprise** (F6), montées sous `/espace-entreprise`.
 *
 * Réutilise le **shell générique** `SpaceLayoutComponent`, paramétré par
 * `ENTERPRISE_SPACE` (fourni via `SPACE_CONFIG`). Toute la branche est protégée
 * par `roleGuard` avec le rôle `entreprise` : sans session → redirection
 * connexion (avec `redirect`) ; connecté sans le rôle → renvoi à l'accueil.
 *
 * Messagerie, Notifications et Profil réutilisent les composants transverses de
 * l'espace client (ils portent sur l'utilisateur, pas sur un espace), montés ici
 * pour rester dans l'espace entreprise. La messagerie est rendue autonome par
 * `SPACE_CONFIG` (aucun lien en dur vers `/mon-espace`).
 */
export const ENTERPRISE_ROUTES: Routes = [
  {
    path: '',
    component: SpaceLayoutComponent,
    canActivate: [roleGuard],
    data: { roles: ['entreprise'] },
    providers: [{ provide: SPACE_CONFIG, useValue: ENTERPRISE_SPACE }],
    children: [
      {
        // F6 — Tableau de bord : accueil de l'espace + accès à une nouvelle demande.
        path: '',
        loadComponent: () =>
          import('./overview/enterprise-overview-page').then(
            (m) => m.EnterpriseOverviewPageComponent,
          ),
        title: 'Espace entreprise — Kaikun 360',
      },
      {
        // F6 — Nouvelle demande de team building (POST /team-building-requests).
        // ⚠️ Déclaré AVANT `demandes/:id` sinon « nouvelle » serait pris pour un id.
        path: 'demandes/nouvelle',
        loadComponent: () =>
          import('./requests/enterprise-request-form-page').then(
            (m) => m.EnterpriseRequestFormPageComponent,
          ),
        title: 'Nouvelle demande — Kaikun 360',
      },
      {
        // F6 — Mes demandes : historique (GET /team-building-requests/mine).
        path: 'demandes',
        loadComponent: () =>
          import('./requests/enterprise-requests-page').then(
            (m) => m.EnterpriseRequestsPageComponent,
          ),
        title: 'Mes demandes — Kaikun 360',
      },
      {
        // F6 — Détail d'une demande + devis (GET /team-building-requests/{id}).
        path: 'demandes/:id',
        loadComponent: () =>
          import('./requests/enterprise-request-detail-page').then(
            (m) => m.EnterpriseRequestDetailPageComponent,
          ),
        title: 'Ma demande — Kaikun 360',
      },
      {
        // Messages — écran transverse (GET /messages), réutilisé, autonome via SPACE_CONFIG.
        path: 'messages',
        loadComponent: () =>
          import('../account/messages/messages-page').then((m) => m.MessagesPageComponent),
        title: 'Mes messages — Kaikun 360',
      },
      {
        // Fil de discussion (GET /messages/{id}).
        path: 'messages/:id',
        loadComponent: () =>
          import('../account/messages/message-thread').then((m) => m.MessageThreadComponent),
        title: 'Conversation — Kaikun 360',
      },
      {
        // Notifications — écran transverse (GET /users/me/notifications).
        path: 'notifications',
        loadComponent: () =>
          import('../account/notifications/notifications-page').then(
            (m) => m.NotificationsPageComponent,
          ),
        title: 'Mes notifications — Kaikun 360',
      },
      {
        // Profil — écran transverse (porte sur l'utilisateur connecté).
        path: 'profil',
        loadComponent: () =>
          import('../account/profile/profile-page').then((m) => m.ProfilePageComponent),
        title: 'Mon profil — Kaikun 360',
      },
    ],
  },
];
