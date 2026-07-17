import { Routes } from '@angular/router';

import { authGuard } from '../../core/guards/auth.guard';
import { AccountLayoutComponent } from '../../layouts/account-layout/account-layout';

/**
 * Routes de l'espace client (F3.1), montées sous `/mon-espace`.
 *
 * Toute la branche est protégée par `authGuard` (posé au niveau du composant de
 * layout) : sans session active, on est redirigé vers la connexion avec l'URL
 * demandée en `redirect`. Les sections se brancheront ici au fil des sous-phases
 * (F3.2 profil, F3.3 demandes, F3.4 réservations, F3.5 favoris, F3.6
 * notifications, F3.7 messages).
 */
export const ACCOUNT_ROUTES: Routes = [
  {
    path: '',
    component: AccountLayoutComponent,
    canActivate: [authGuard],
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
        // F3.4 — Réservations : suivi + annulation (GET /bookings/my).
        path: 'reservations',
        loadComponent: () =>
          import('./bookings/bookings-page').then((m) => m.BookingsPageComponent),
        title: 'Mes réservations — Kaikun 360',
      },
      {
        // F3.2 — Profil : identité, pièces justificatives, suppression du compte.
        path: 'profil',
        loadComponent: () =>
          import('./profile/profile-page').then((m) => m.ProfilePageComponent),
        title: 'Mon profil — Kaikun 360',
      },
    ],
  },
];
