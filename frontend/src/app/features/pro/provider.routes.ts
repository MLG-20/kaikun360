import { Routes } from '@angular/router';

import { roleGuard } from '../../core/guards/role.guard';
import { SpaceLayoutComponent } from '../../layouts/space-layout/space-layout';
import { SPACE_CONFIG } from '../../layouts/space-layout/space.config';
import { PROVIDER_SPACE } from './provider-space';

/**
 * Routes de l'**espace prestataire** (F5), montées sous `/espace-prestataire`.
 *
 * Réutilise le **shell générique** `SpaceLayoutComponent`, paramétré par
 * `PROVIDER_SPACE` (fourni via `SPACE_CONFIG`). Toute la branche est protégée
 * par `roleGuard` avec le rôle `prestataire` : sans session → redirection
 * connexion (avec `redirect`) ; connecté sans le rôle → renvoi vers son propre
 * espace.
 *
 * Les rubriques se branchent au fil des sous-phases : tableau de bord (F5.1) est
 * en place ; Mes services, Disponibilités (F5.4), Missions (F5.2), Avis (F5.5)
 * et Revenus (F5.3) suivront.
 */
export const PROVIDER_ROUTES: Routes = [
  {
    path: '',
    component: SpaceLayoutComponent,
    canActivate: [roleGuard],
    data: { roles: ['prestataire'] },
    providers: [{ provide: SPACE_CONFIG, useValue: PROVIDER_SPACE }],
    children: [
      {
        // F5.1 — Tableau de bord : profil prestataire + statut (GET /providers/mine).
        path: '',
        loadComponent: () =>
          import('./overview/provider-overview-page').then(
            (m) => m.ProviderOverviewPageComponent,
          ),
        title: 'Espace prestataire — Kaikun 360',
      },
      {
        // F5 — Mes services : édition du profil + certifications (PUT /providers/mine,
        // POST/DELETE /providers/certifications).
        path: 'services',
        loadComponent: () =>
          import('./services/provider-services-page').then(
            (m) => m.ProviderServicesPageComponent,
          ),
        title: 'Mes services — Kaikun 360',
      },
      {
        // F5.6 — Mes offres : liste des véhicules & expériences déposés
        // (GET /vehicles/mine, GET /experiences/mine) avec leur statut.
        path: 'offres',
        loadComponent: () =>
          import('./offers/provider-offers-page').then(
            (m) => m.ProviderOffersPageComponent,
          ),
        title: 'Mes offres — Kaikun 360',
      },
      {
        // F5.6 — Dépôt d'un véhicule (POST /vehicles).
        path: 'offres/vehicule/nouveau',
        loadComponent: () =>
          import('./offers/provider-vehicle-form-page').then(
            (m) => m.ProviderVehicleFormPageComponent,
          ),
        title: 'Déposer un véhicule — Kaikun 360',
      },
      {
        // F5.6 — Édition d'un véhicule (PATCH /vehicles/{id}).
        path: 'offres/vehicule/:id/modifier',
        loadComponent: () =>
          import('./offers/provider-vehicle-form-page').then(
            (m) => m.ProviderVehicleFormPageComponent,
          ),
        title: 'Modifier le véhicule — Kaikun 360',
      },
      {
        // F8.23 — Programmation d'un départ (POST /mobility-services).
        //
        // ⚠️ Ces deux routes ferment le trou le plus structurel qui restait :
        // `mobility_services` était en LECTURE SEULE côté serveur, donc le
        // catalogue public `/mobilite` ne pouvait être alimenté que par le
        // seeder — aucune navette AIBD, aucune liaison n'était mettable en vente.
        path: 'offres/depart/nouveau',
        loadComponent: () =>
          import('./offers/provider-departure-form-page').then(
            (m) => m.ProviderDepartureFormPageComponent,
          ),
        title: 'Programmer un départ — Kaikun 360',
      },
      {
        // F8.23 — Correction d'un départ (PATCH /mobility-services/{id}).
        path: 'offres/depart/:id/modifier',
        loadComponent: () =>
          import('./offers/provider-departure-form-page').then(
            (m) => m.ProviderDepartureFormPageComponent,
          ),
        title: 'Modifier le départ — Kaikun 360',
      },
      {
        // F5.6 — Dépôt d'une expérience touristique (POST /experiences).
        path: 'offres/experience/nouvelle',
        loadComponent: () =>
          import('./offers/provider-experience-form-page').then(
            (m) => m.ProviderExperienceFormPageComponent,
          ),
        title: 'Déposer un circuit — Kaikun 360',
      },
      {
        // F8.19 — Édition d'un circuit (PATCH /experiences/{id}). La route
        // n'existait ni ici ni côté serveur : un circuit déposé était définitif,
        // et ne pouvait donc jamais être illustré après coup.
        path: 'offres/experience/:id/modifier',
        loadComponent: () =>
          import('./offers/provider-experience-form-page').then(
            (m) => m.ProviderExperienceFormPageComponent,
          ),
      },
      {
        // F5.2 — Missions reçues (GET /provider-missions/mine + transitions).
        path: 'missions',
        loadComponent: () =>
          import('./missions/provider-missions-page').then(
            (m) => m.ProviderMissionsPageComponent,
          ),
        title: 'Missions reçues — Kaikun 360',
      },
      {
        // F5.4 — Disponibilités (planning hebdo + indisponibilités).
        path: 'disponibilites',
        loadComponent: () =>
          import('./availability/provider-availability-page').then(
            (m) => m.ProviderAvailabilityPageComponent,
          ),
        title: 'Disponibilités — Kaikun 360',
      },
      {
        // F5.5 — Avis reçus (GET /providers/reviews).
        path: 'avis',
        loadComponent: () =>
          import('./reviews/provider-reviews-page').then(
            (m) => m.ProviderReviewsPageComponent,
          ),
        title: 'Avis reçus — Kaikun 360',
      },
      {
        // F5.3 — Revenus & commissions (GET /provider-missions/earnings).
        path: 'revenus',
        loadComponent: () =>
          import('./earnings/provider-earnings-page').then(
            (m) => m.ProviderEarningsPageComponent,
          ),
        title: 'Revenus & commissions — Kaikun 360',
      },
      {
        // ⚠️ **F8.12.c — sans ces deux routes, la messagerie ment.** Depuis que
        // l'agent peut faire entrer un propriétaire ou un prestataire dans un
        // fil, le tiers reçoit une notification… et n'avait AUCUN écran pour
        // lire le message : les écrans de messagerie n'étaient montés que dans
        // l'espace client et l'espace entreprise. Les composants sont
        // transverses et autonomes (ils lisent `SPACE_CONFIG`) : les monter ici
        // suffit, et les liens restent dans l'espace courant.
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
        // Profil — écran transverse monté DANS l'espace prestataire (réutilise le
        // composant de l'espace client : il porte sur l'utilisateur connecté).
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
