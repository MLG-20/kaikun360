import { Routes } from '@angular/router';

import { platformGateGuard } from '../../core/guards/platform-gate.guard';
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
    // Fermeture d'accès (2026-08-14) : le gate passe AVANT le rôle.
    canActivate: [platformGateGuard, roleGuard],
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
        // F8.14 — Réservations : écrans transverses réutilisés (ils lisent
        // `SPACE_CONFIG`, aucun lien n'y est écrit en dur sur `/mon-espace`).
        // ⚠️ Sans ces trois routes, l'e-mail « votre devis est accepté »
        // renverrait l'entreprise vers l'espace client, gardé par le rôle
        // `client` : un mur, au moment précis où elle veut payer.
        path: 'reservations',
        loadComponent: () =>
          import('../account/bookings/bookings-page').then((m) => m.BookingsPageComponent),
        title: 'Mes réservations — Kaikun 360',
      },
      {
        path: 'reservations/:id',
        loadComponent: () =>
          import('../account/bookings/booking-detail-page').then(
            (m) => m.BookingDetailPageComponent,
          ),
        title: 'Ma réservation — Kaikun 360',
      },
      {
        // Écran DÉDIÉ au règlement : payer engage de l'argent, on montre ce
        // qui est dû avant de sortir vers le prestataire de paiement.
        path: 'reservations/:id/paiement',
        loadComponent: () =>
          import('../account/bookings/booking-payment-page').then(
            (m) => m.BookingPaymentPageComponent,
          ),
        title: 'Régler ma réservation — Kaikun 360',
      },
      {
        // F8.15.a — Donner son avis. Montée ici aussi parce que la carte de
        // réservation porte le bouton dans TOUS les espaces : sans la route,
        // il mènerait au 404. Une entreprise réserve des séminaires (qui ne se
        // notent pas), mais rien ne l'empêche de louer un véhicule.
        path: 'reservations/:id/avis',
        loadComponent: () =>
          import('../account/bookings/booking-review-page').then(
            (m) => m.BookingReviewPageComponent,
          ),
        title: 'Donner mon avis — Kaikun 360',
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
