import { Routes } from '@angular/router';

import { platformGateGuard } from '../../core/guards/platform-gate.guard';
import { roleGuard } from '../../core/guards/role.guard';
import { SpaceLayoutComponent } from '../../layouts/space-layout/space-layout';
import { SPACE_CONFIG } from '../../layouts/space-layout/space.config';
import { DIASPORA_SPACE } from './diaspora-space';

/**
 * Routes de l'**espace diaspora** (F18, 2026-08-22), montées sous
 * `/espace-diaspora`.
 *
 * Séparé de l'espace client (où il vivait depuis F3.8, sous
 * `/mon-espace/diaspora`) : réutilise le **shell générique**
 * `SpaceLayoutComponent`, paramétré par `DIASPORA_SPACE` (fourni via
 * `SPACE_CONFIG`), sur le même patron que `owner.routes.ts`. Toute la branche
 * est protégée par `roleGuard` avec le rôle `diaspora` : sans session →
 * redirection connexion (avec `redirect`) ; connecté sans le rôle → renvoi à
 * l'accueil.
 *
 * ⚠️ Ne pas confondre avec `/diaspora` (page marketing publique,
 * `diaspora-page/`) : deux routes distinctes, l'une publique, l'autre
 * connectée.
 */
export const DIASPORA_ROUTES: Routes = [
  {
    path: '',
    component: SpaceLayoutComponent,
    // Fermeture d'accès (2026-08-14) : le gate passe AVANT le rôle.
    canActivate: [platformGateGuard, roleGuard],
    data: { roles: ['diaspora'] },
    providers: [{ provide: SPACE_CONFIG, useValue: DIASPORA_SPACE }],
    children: [
      {
        // F3.8 — Liste des projets, devenue la page d'ACCUEIL de l'espace
        // (GET /diaspora-projects/mine).
        path: '',
        loadComponent: () =>
          import('./diaspora-projects/diaspora-projects-page').then(
            (m) => m.DiasporaProjectsPageComponent,
          ),
        title: 'Espace diaspora — Kaikun 360',
      },
      {
        // F3.8 — Lancement d'un projet diaspora (POST /diaspora-projects).
        // ⚠️ Déclaré AVANT `:id` sinon « nouveau » serait pris pour un id.
        path: 'nouveau',
        loadComponent: () =>
          import('./diaspora-projects/diaspora-project-form-page').then(
            (m) => m.DiasporaProjectFormPageComponent,
          ),
        title: 'Lancer un projet diaspora — Kaikun 360',
      },
      {
        // Messagerie — composant TRANSVERSE réutilisé (comme chez le
        // propriétaire), monté ici pour ne jamais éjecter vers un autre espace.
        // ⚠️ Déclaré AVANT `:id` — sinon `/messages` est pris pour un projet
        // dont l'id serait « messages » (bug trouvé en vérifiant : la route
        // renvoyait « Ce projet est introuvable »).
        path: 'messages',
        loadComponent: () =>
          import('../account/messages/messages-page').then((m) => m.MessagesPageComponent),
        title: 'Mes messages — Kaikun 360',
      },
      {
        path: 'messages/:id',
        loadComponent: () =>
          import('../account/messages/message-thread').then((m) => m.MessageThreadComponent),
        title: 'Conversation — Kaikun 360',
      },
      {
        // Corbeille — composant TRANSVERSE (lit la corbeille du compte connecté).
        // ⚠️ Déclaré AVANT `:id`, même raison que `messages` ci-dessus.
        path: 'corbeille',
        loadComponent: () => import('../trash/trash-page').then((m) => m.TrashPageComponent),
        title: 'Corbeille — Kaikun 360',
      },
      {
        // Profil — écran transverse monté DANS l'espace diaspora : cliquer
        // « Mon profil » ne doit JAMAIS éjecter vers `/mon-espace`.
        // ⚠️ Déclaré AVANT `:id`, même raison que `messages` ci-dessus.
        path: 'profil',
        loadComponent: () =>
          import('../account/profile/profile-page').then((m) => m.ProfilePageComponent),
        title: 'Mon profil — Kaikun 360',
      },
      {
        // ⚠️ Déclaré AVANT `:id`, même raison que `messages` ci-dessus.
        path: 'notifications',
        loadComponent: () =>
          import('../account/notifications/notifications-page').then(
            (m) => m.NotificationsPageComponent,
          ),
        title: 'Mes notifications — Kaikun 360',
      },
      {
        // F3.8 — Détail d'un projet + rapports de suivi
        // (GET /diaspora-projects/{id} + /reports).
        // ⚠️ Déclaré EN DERNIER : `:id` matche n'importe quel segment, il doit
        // laisser passer tous les chemins littéraux ci-dessus en premier.
        path: ':id',
        loadComponent: () =>
          import('./diaspora-projects/diaspora-project-detail-page').then(
            (m) => m.DiasporaProjectDetailPageComponent,
          ),
        title: 'Projet diaspora — Kaikun 360',
      },
    ],
  },
];
