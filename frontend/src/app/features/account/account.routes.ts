import { Routes } from '@angular/router';

import { platformGateGuard } from '../../core/guards/platform-gate.guard';
import { roleGuard } from '../../core/guards/role.guard';
import { SpaceLayoutComponent } from '../../layouts/space-layout/space-layout';
import { SPACE_CONFIG } from '../../layouts/space-layout/space.config';
import { CLIENT_SPACE } from './client-space';

/**
 * Routes de l'espace client (F3.1), montées sous `/mon-espace`.
 *
 * Depuis F4, l'espace utilise le **shell générique** `SpaceLayoutComponent`,
 * paramétré par `CLIENT_SPACE` (fourni via le jeton `SPACE_CONFIG`).
 *
 * ⚠️ **Cloisonnement par rôle** : la branche exige le rôle `client` (et non un
 * simple `authGuard`), comme `/espace-proprietaire` exige `proprietaire`. Chaque
 * espace est autonome ; un compte qui n'est que propriétaire est renvoyé vers le
 * sien. Un compte réellement multi-rôles (client ET propriétaire) conserve
 * naturellement l'accès aux deux.
 */
export const ACCOUNT_ROUTES: Routes = [
  {
    path: '',
    component: SpaceLayoutComponent,
    // Fermeture d'accès (2026-08-14) : le gate passe AVANT le rôle — un
    // visiteur non connecté qui vise cet espace doit atterrir sur la liste
    // d'attente, pas sur l'écran de connexion, tant que c'est fermé.
    canActivate: [platformGateGuard, roleGuard],
    data: { roles: ['client'] },
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
        // F8.6 — Régler une réservation. Écran DÉDIÉ et non un bouton dans une
        // liste : payer engage de l'argent, le client doit voir ce qu'il doit
        // avant qu'on ne le sorte du site vers le prestataire de paiement.
        path: 'reservations/:id/paiement',
        loadComponent: () =>
          import('./bookings/booking-payment-page').then((m) => m.BookingPaymentPageComponent),
        title: 'Régler ma réservation — Kaikun 360',
      },
      {
        // F8.15.a — Donner son avis sur une réservation terminée (POST /reviews,
        // écrit depuis B12.2 et jusqu'ici sans aucun appelant). Écran dédié,
        // comme le règlement : écrire un avis demande de se souvenir du séjour.
        path: 'reservations/:id/avis',
        loadComponent: () =>
          import('./bookings/booking-review-page').then((m) => m.BookingReviewPageComponent),
        title: 'Donner mon avis — Kaikun 360',
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
        // F11.5 — Corbeille : ce que le client a rangé de ses listes. Composant
        // TRANSVERSE, déjà monté chez le propriétaire et le prestataire depuis
        // F11.4 (il lit la corbeille du compte connecté, pas celle d'un espace).
        // ⚠️ Ici elle ne contient QUE des dossiers masqués — un client ne
        // possède aucune annonce — donc aucun compte à rebours : rien n'y est
        // jamais supprimé, et l'écran le dit lui-même.
        path: 'corbeille',
        loadComponent: () => import('../trash/trash-page').then((m) => m.TrashPageComponent),
        title: 'Corbeille — Kaikun 360',
      },
      {
        // F3.2 — Profil : identité, pièces justificatives, suppression du compte.
        path: 'profil',
        loadComponent: () => import('./profile/profile-page').then((m) => m.ProfilePageComponent),
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
