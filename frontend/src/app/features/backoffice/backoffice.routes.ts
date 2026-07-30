import { Routes } from '@angular/router';

import { permissionGuard } from '../../core/guards/permission.guard';
import { roleGuard } from '../../core/guards/role.guard';
import { BackofficeLayoutComponent } from '../../layouts/backoffice-layout/backoffice-layout';
import { permissionsFor } from './backoffice-permissions';

/**
 * Routes du **back-office** (F7.1.e) — poste de commandement de l'équipe.
 *
 * Montées sous `/back-office`, toutes protégées par `roleGuard` avec les rôles
 * de l'équipe (agent / admin / super_admin). Un compte sans l'un de ces rôles
 * est renvoyé vers son propre espace (comportement du guard). Le shell est
 * **dédié** (`BackofficeLayoutComponent`), distinct de celui des espaces.
 *
 * **Deux étages de garde** (F7.4.a) :
 *   1. la racine vérifie le RÔLE — on entre, ou non, dans la salle de contrôle ;
 *   2. chaque rubrique vérifie la PERMISSION du geste qu'elle porte, via
 *      `permissionGuard` et la table partagée `backoffice-permissions.ts` (même
 *      source que le filtrage du rail, pour qu'un lien masqué ne reste pas
 *      atteignable à l'URL). Les fiches de détail héritent de la liste de leur
 *      rubrique : `construction/:id` est gardée comme `construction`.
 *
 * Ce second étage répond au CDC §7 (« Agent Kaikun : accès financier limité ») ;
 * il ne remplace pas les `can:` du serveur, qui restent la sécurité réelle.
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
        canActivate: [permissionGuard],
        data: { permissions: permissionsFor('validation') },
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
        canActivate: [permissionGuard],
        data: { permissions: permissionsFor('catalogues') },
        loadComponent: () =>
          import('./catalogues/backoffice-catalogues-page').then(
            (m) => m.BackofficeCataloguesPageComponent,
          ),
        title: 'Catalogues — Back-office Kaikun 360',
      },
      {
        // F7.2.j — Mobilité : flotte (conformité assurance/chauffeur/pirogue)
        // + départs programmés et leur remplissage.
        path: 'mobilite',
        canActivate: [permissionGuard],
        data: { permissions: permissionsFor('mobilite') },
        loadComponent: () =>
          import('./mobility/backoffice-mobility-page').then(
            (m) => m.BackofficeMobilityPageComponent,
          ),
        title: 'Mobilité — Back-office Kaikun 360',
      },
      {
        // F7.2.k — Tourisme : circuits + remplissage, couverture par
        // destination, partenaires guides & restaurants.
        path: 'tourisme',
        canActivate: [permissionGuard],
        data: { permissions: permissionsFor('tourisme') },
        loadComponent: () =>
          import('./tourism/backoffice-tourism-page').then(
            (m) => m.BackofficeTourismPageComponent,
          ),
        title: 'Tourisme — Back-office Kaikun 360',
      },
      {
        // F7.2.c — Nuitées : calendrier des séjours + check-in/out + ménage.
        path: 'nuitees',
        canActivate: [permissionGuard],
        data: { permissions: permissionsFor('nuitees') },
        loadComponent: () =>
          import('./stays/backoffice-stays-page').then((m) => m.BackofficeStaysPageComponent),
        title: 'Nuitées — Back-office Kaikun 360',
      },
      {
        // F7.2.d — Paiements : supervision + confirmation manuelle Wave/OM + remboursement.
        path: 'paiements',
        canActivate: [permissionGuard],
        data: { permissions: permissionsFor('paiements') },
        loadComponent: () =>
          import('./payments/backoffice-payments-page').then(
            (m) => m.BackofficePaymentsPageComponent,
          ),
        title: 'Paiements — Back-office Kaikun 360',
      },
      {
        // F7.3.c — Construction : l'ancien écran « Dossiers » à onglets est
        // scindé, chaque métier ayant sa rubrique au rail.
        path: 'construction',
        canActivate: [permissionGuard],
        data: { permissions: permissionsFor('construction') },
        loadComponent: () =>
          import('./construction/backoffice-construction-page').then(
            (m) => m.BackofficeConstructionPageComponent,
          ),
        title: 'Construction — Back-office Kaikun 360',
      },
      {
        // F7.3.b — Fiche d'une demande de construction : projet, demandeur,
        // jalons (lecture seule) et comptes rendus de chantier.
        path: 'construction/:id',
        canActivate: [permissionGuard],
        data: { permissions: permissionsFor('construction') },
        loadComponent: () =>
          import('./construction/detail/backoffice-construction-detail-page').then(
            (m) => m.BackofficeConstructionDetailPageComponent,
          ),
        title: 'Demande de construction — Back-office Kaikun 360',
      },
      {
        // F7.3.c — Gestion locative : mandats de tous les propriétaires.
        path: 'gestion-locative',
        canActivate: [permissionGuard],
        data: { permissions: permissionsFor('gestion-locative') },
        loadComponent: () =>
          import('./rental/backoffice-rental-page').then((m) => m.BackofficeRentalPageComponent),
        title: 'Gestion locative — Back-office Kaikun 360',
      },
      {
        // F7.3.a — Fiche d'un mandat, PILOTABLE (loyers, incidents, dépenses,
        // reversements, rapport mensuel).
        path: 'gestion-locative/:id',
        canActivate: [permissionGuard],
        data: { permissions: permissionsFor('gestion-locative') },
        loadComponent: () =>
          import('./rental/detail/backoffice-mandate-detail-page').then(
            (m) => m.BackofficeMandateDetailPageComponent,
          ),
        title: 'Mandat de gestion — Back-office Kaikun 360',
      },
      {
        // F7.2.f — Comptes & documents : annuaire des comptes (statut/rôle/pièces)
        // + vue documentaire transverse (KYC, biens, certifs, reversements).
        path: 'comptes',
        canActivate: [permissionGuard],
        data: { permissions: permissionsFor('comptes') },
        loadComponent: () =>
          import('./accounts/backoffice-accounts-page').then(
            (m) => m.BackofficeAccountsPageComponent,
          ),
        title: 'Comptes & documents — Back-office Kaikun 360',
      },
      {
        // F7.2.f — Fiche détaillée d'un compte (toutes ses infos + pilotage).
        path: 'comptes/:id',
        canActivate: [permissionGuard],
        data: { permissions: permissionsFor('comptes') },
        loadComponent: () =>
          import('./accounts/detail/backoffice-account-detail-page').then(
            (m) => m.BackofficeAccountDetailPageComponent,
          ),
        title: 'Fiche compte — Back-office Kaikun 360',
      },
      {
        // F7.2.g — Avis & qualité : modération des avis + notation/sanctions prestataires.
        path: 'qualite',
        canActivate: [permissionGuard],
        data: { permissions: permissionsFor('qualite') },
        loadComponent: () =>
          import('./quality/backoffice-quality-page').then(
            (m) => m.BackofficeQualityPageComponent,
          ),
        title: 'Avis & qualité — Back-office Kaikun 360',
      },
      {
        // F7.2.h — Team building : file des demandes entreprises + fiche
        // (devis pack + affectation prestataires).
        path: 'team-building',
        canActivate: [permissionGuard],
        data: { permissions: permissionsFor('team-building') },
        loadComponent: () =>
          import('./team-building/backoffice-team-building-page').then(
            (m) => m.BackofficeTeamBuildingPageComponent,
          ),
        title: 'Team building — Back-office Kaikun 360',
      },
      {
        // F7.2.h — Fiche d'une demande de team building (devis + prestataires).
        path: 'team-building/:id',
        canActivate: [permissionGuard],
        data: { permissions: permissionsFor('team-building') },
        loadComponent: () =>
          import('./team-building/detail/backoffice-team-building-detail-page').then(
            (m) => m.BackofficeTeamBuildingDetailPageComponent,
          ),
        title: 'Demande team building — Back-office Kaikun 360',
      },
      {
        // F7.2.i — Diaspora : file priorisée des dossiers à distance + fiche
        // (priorité, affectation d'agent, statut, rapports de suivi).
        path: 'diaspora',
        canActivate: [permissionGuard],
        data: { permissions: permissionsFor('diaspora') },
        loadComponent: () =>
          import('./diaspora/backoffice-diaspora-page').then(
            (m) => m.BackofficeDiasporaPageComponent,
          ),
        title: 'Diaspora — Back-office Kaikun 360',
      },
      {
        // F7.2.i — Fiche d'un dossier diaspora.
        path: 'diaspora/:id',
        canActivate: [permissionGuard],
        data: { permissions: permissionsFor('diaspora') },
        loadComponent: () =>
          import('./diaspora/detail/backoffice-diaspora-detail-page').then(
            (m) => m.BackofficeDiasporaDetailPageComponent,
          ),
        title: 'Dossier diaspora — Back-office Kaikun 360',
      },
      {
        // F7.2.l — Paramètres & contenu : réglages (commissions, tarifs),
        // notifications, pages & FAQ, référentiels (villes, catégories).
        path: 'parametres',
        canActivate: [permissionGuard],
        data: { permissions: permissionsFor('parametres') },
        loadComponent: () =>
          import('./settings/backoffice-settings-page').then(
            (m) => m.BackofficeSettingsPageComponent,
          ),
        title: 'Paramètres & contenu — Back-office Kaikun 360',
      },
      {
        // F7.1.f — Équipe : annuaire, enrôlement, pilotage rôle/statut.
        path: 'equipe',
        canActivate: [permissionGuard],
        data: { permissions: permissionsFor('equipe') },
        loadComponent: () =>
          import('./team/backoffice-team-page').then((m) => m.BackofficeTeamPageComponent),
        title: 'Équipe — Back-office Kaikun 360',
      },
      {
        // F7.1.g — Permissions : matrice de délégation des dossiers par agent.
        path: 'permissions',
        canActivate: [permissionGuard],
        data: { permissions: permissionsFor('permissions') },
        loadComponent: () =>
          import('./permissions/backoffice-permissions-page').then(
            (m) => m.BackofficePermissionsPageComponent,
          ),
        title: 'Permissions — Back-office Kaikun 360',
      },
      {
        // F7.1.h — Pointeuse : présences (perso) + feuille d'équipe + export.
        path: 'pointeuse',
        canActivate: [permissionGuard],
        data: { permissions: permissionsFor('pointeuse') },
        loadComponent: () =>
          import('./attendance/backoffice-attendance-page').then(
            (m) => m.BackofficeAttendancePageComponent,
          ),
        title: 'Pointeuse — Back-office Kaikun 360',
      },
    ],
  },
];
