import { Routes } from '@angular/router';

import { authGuard } from '../../core/guards/auth.guard';
import { SpaceLayoutComponent } from '../../layouts/space-layout/space-layout';
import { SPACE_CONFIG } from '../../layouts/space-layout/space.config';
import { CLIENT_SPACE } from './client-space';

/**
 * Routes de l'espace client (F3.1), montées sous `/mon-espace`.
 *
 * Depuis F4, l'espace utilise le **shell générique** `SpaceLayoutComponent`,
 * paramétré par `CLIENT_SPACE` (fourni via le jeton `SPACE_CONFIG`). Toute la
 * branche est protégée par `authGuard` : sans session active, on est redirigé
 * vers la connexion avec l'URL demandée en `redirect`.
 */
export const ACCOUNT_ROUTES: Routes = [
  {
    path: '',
    component: SpaceLayoutComponent,
    canActivate: [authGuard],
    providers: [{ provide: SPACE_CONFIG, useValue: CLIENT_SPACE }],
    children: [
      {
        path: '',
        loadComponent: () =>
          import('./overview/account-overview-page').then((m) => m.AccountOverviewPageComponent),
        title: 'Mon espace — Kaikun 360',
      },
      {
        // F3.3 — Mes demandes : suivi des demandes de service (GET /requests/my).
        path: 'demandes',
        loadComponent: () =>
          import('./requests/requests-page').then((m) => m.RequestsPageComponent),
        title: 'Mes demandes — Kaikun 360',
      },
      {
        // F3.3 — Détail d'une demande (GET /requests/{id}, propriétaire seul).
        path: 'demandes/:id',
        loadComponent: () =>
          import('./requests/request-detail-page').then((m) => m.RequestDetailPageComponent),
        title: 'Ma demande — Kaikun 360',
      },
      {
        // F3.4 — Réservations : suivi + annulation (GET /bookings/my).
        path: 'reservations',
        loadComponent: () =>
          import('./bookings/bookings-page').then((m) => m.BookingsPageComponent),
        title: 'Mes réservations — Kaikun 360',
      },
      {
        // F3.4 — Détail d'une réservation (GET /bookings/{id}, titulaire seul).
        path: 'reservations/:id',
        loadComponent: () =>
          import('./bookings/booking-detail-page').then((m) => m.BookingDetailPageComponent),
        title: 'Ma réservation — Kaikun 360',
      },
      {
        // F3.5 — Favoris : biens sauvegardés (GET /favorites, retrait).
        path: 'favoris',
        loadComponent: () =>
          import('./favorites/favorites-page').then((m) => m.FavoritesPageComponent),
        title: 'Mes favoris — Kaikun 360',
      },
      {
        // F3.6 — Notifications : centre de notifications (GET /users/me/notifications).
        path: 'notifications',
        loadComponent: () =>
          import('./notifications/notifications-page').then((m) => m.NotificationsPageComponent),
        title: 'Mes notifications — Kaikun 360',
      },
      {
        // F3.7 — Messages : liste des conversations (GET /messages).
        path: 'messages',
        loadComponent: () =>
          import('./messages/messages-page').then((m) => m.MessagesPageComponent),
        title: 'Mes messages — Kaikun 360',
      },
      {
        // F3.7 — Fil de discussion : messages + réponse (GET /messages/{id}).
        path: 'messages/:id',
        loadComponent: () =>
          import('./messages/message-thread').then((m) => m.MessageThreadComponent),
        title: 'Conversation — Kaikun 360',
      },
      {
        // F3.2 — Profil : identité, pièces justificatives, suppression du compte.
        path: 'profil',
        loadComponent: () =>
          import('./profile/profile-page').then((m) => m.ProfilePageComponent),
        title: 'Mon profil — Kaikun 360',
      },
      {
        // Aide : mode d'emploi de l'espace (à quoi sert chaque rubrique).
        path: 'aide',
        loadComponent: () => import('./help/help-page').then((m) => m.HelpPageComponent),
        title: 'Aide — Kaikun 360',
      },
    ],
  },
];
