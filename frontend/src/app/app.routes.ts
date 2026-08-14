import { Routes } from '@angular/router';

import { platformGateGuard } from './core/guards/platform-gate.guard';
import { MainLayoutComponent } from './layouts/main-layout/main-layout';

/**
 * Routeur racine (F1.1).
 *
 * Deux branches de layout :
 *   - `/auth/*` → layout d'authentification (chargé à la demande) ;
 *   - le reste → layout principal (en-tête/pied), avec l'accueil pour l'instant.
 *
 * `auth` est déclaré AVANT la branche `''` (qui matche par préfixe) pour être
 * résolu en priorité.
 *
 * ## Référencement (F9.1) — lire avant d'ajouter une route
 *
 * Chaque route **publique** porte un `data: { seo: … }` : une description de
 * 120–160 caractères servie au robot avant même que la page ait chargé ses
 * données. Les fiches l'affinent ensuite avec leur contenu réel (titre du bien,
 * photo de couverture) via `SeoService.apply()`.
 *
 * ⚠️ **Une route SANS `data.seo` est mise hors index** (`noindex, follow`) par
 * `SeoTitleStrategy`. C'est délibéré : les quatre espaces connectés et le
 * back-office n'ont rien à faire dans un moteur, et cette règle les protège par
 * défaut plutôt que par vigilance. Conséquence : **oublier `seo` sur une page
 * publique la rend invisible à Google**. C'est le sens de l'erreur qu'on
 * préfère.
 */
export const routes: Routes = [
  {
    path: 'auth',
    loadChildren: () => import('./features/auth/auth.routes').then((m) => m.AUTH_ROUTES),
  },
  {
    // Espace client authentifié (F3) : layout à navigation latérale, protégé
    // par `authGuard`. Déclaré avant la branche `''` (préfixe) comme `auth`.
    path: 'mon-espace',
    loadChildren: () => import('./features/account/account.routes').then((m) => m.ACCOUNT_ROUTES),
  },
  {
    // Espace propriétaire authentifié (F4) : même shell app-shell générique,
    // protégé par `roleGuard` (rôle `proprietaire`). Déclaré avant `''`.
    path: 'espace-proprietaire',
    loadChildren: () => import('./features/owner/owner.routes').then((m) => m.OWNER_ROUTES),
  },
  {
    // Espace prestataire authentifié (F5) : même shell app-shell générique,
    // protégé par `roleGuard` (rôle `prestataire`). Déclaré avant `''`.
    path: 'espace-prestataire',
    loadChildren: () => import('./features/pro/provider.routes').then((m) => m.PROVIDER_ROUTES),
  },
  {
    // Espace entreprise authentifié (F6) : même shell app-shell générique,
    // protégé par `roleGuard` (rôle `entreprise`). Déclaré avant `''`.
    path: 'espace-entreprise',
    loadChildren: () =>
      import('./features/enterprise/enterprise.routes').then((m) => m.ENTERPRISE_ROUTES),
  },
  {
    // Back-office (F7) : poste de commandement de l'équipe. Shell DÉDIÉ et
    // indépendant (pas le shell des espaces), protégé par `roleGuard` avec les
    // rôles staff (agent/admin/super_admin). Déclaré avant `''`.
    path: 'back-office',
    loadChildren: () =>
      import('./features/backoffice/backoffice.routes').then((m) => m.BACKOFFICE_ROUTES),
  },
  {
    path: '',
    component: MainLayoutComponent,
    // Fermeture d'accès avant ouverture (2026-08-14) : s'applique à TOUTES les
    // pages publiques ci-dessous, sauf celles marquées `data.gateExempt`
    // (liste d'attente, contact, FAQ, pages légales).
    canActivateChild: [platformGateGuard],
    children: [
      {
        path: '',
        loadComponent: () => import('./features/home/home-page').then((m) => m.HomePageComponent),
        title: 'Kaikun 360 — Immobilier, tourisme & services au Sénégal',
        data: {
          seo: {
            description:
              'Immobilier vérifié, nuitées, construction, gestion locative, tourisme et mobilité au Sénégal : une seule plateforme pour habiter, investir et voyager.',
          },
        },
      },
      {
        // Page de résultats du moteur de recherche (F2.1). L'univers et les
        // filtres sont portés par les query params (ex. /recherche?univers=nuitees).
        path: 'recherche',
        loadComponent: () =>
          import('./features/catalog/catalog-page').then((m) => m.CatalogPageComponent),
        title: 'Recherche — Kaikun 360',
        data: {
          seo: {
            description:
              'Recherchez un bien, un séjour, une expérience ou un trajet parmi les offres vérifiées de Kaikun 360, partout au Sénégal.',
            // ⚠️ HORS INDEX à dessein : les filtres vivent dans l'URL depuis
            // `34fbe37`, ce qui rend le nombre d'URL distinctes combinatoire
            // pour un contenu qui est toujours celui des catalogues. Ce sont
            // les catalogues (`/immobilier`, `/nuitees`…) qu'on veut voir
            // remonter, pas leurs vues filtrées. `follow` reste actif : le
            // robot suit les liens de résultats jusqu'aux fiches.
            index: false,
          },
        },
      },
      {
        // Univers Immobilier (F2.3) : page vitrine + fiche détaillée d'un bien.
        path: 'immobilier',
        loadComponent: () =>
          import('./features/immo/property-list/property-list-page').then(
            (m) => m.PropertyListPageComponent,
          ),
        title: 'Immobilier vérifié — Kaikun 360',
        data: {
          seo: {
            description:
              'Villas, appartements et terrains vérifiés à Dakar, Saly, Thiès et dans tout le Sénégal. Titres contrôlés, visites organisées, achat à distance possible.',
          },
        },
      },
      {
        // Dépôt de bien par un propriétaire (F2.7) : formulaire + sélecteurs géo.
        // Déclaré AVANT `immobilier/:id` n'est pas nécessaire (chemin distinct),
        // mais on le groupe avec l'univers Immobilier.
        path: 'deposer-un-bien',
        loadComponent: () =>
          import('./features/immo/property-deposit/property-deposit-page').then(
            (m) => m.PropertyDepositPageComponent,
          ),
        title: 'Déposer un bien — Kaikun 360',
        data: {
          seo: {
            description:
              'Confiez votre bien à Kaikun 360 : dépôt en ligne, vérification du dossier par nos agents, publication au catalogue et mise en relation avec des acheteurs.',
          },
        },
      },
      {
        // Comparateur de biens (F8.15.e). ⚠️ DOIT être déclaré AVANT
        // `immobilier/:id`, sinon « comparer » serait pris pour un identifiant
        // de bien et la page de fiche répondrait « introuvable ».
        path: 'immobilier/comparer',
        loadComponent: () =>
          import('./features/immo/property-compare/property-compare-page').then(
            (m) => m.PropertyComparePageComponent,
          ),
        title: 'Comparer des biens — Kaikun 360',
        data: {
          seo: {
            description:
              'Comparez côte à côte jusqu\'à quatre biens immobiliers : prix, localisation, type et niveau de vérification, sur un seul écran.',
            // Le contenu dépend entièrement d'une sélection stockée dans le
            // navigateur : pour un robot, la page est toujours vide.
            index: false,
          },
        },
      },
      {
        path: 'immobilier/:id',
        loadComponent: () =>
          import('./features/immo/property-detail/property-detail-page').then(
            (m) => m.PropertyDetailPageComponent,
          ),
        title: 'Bien immobilier — Kaikun 360',
        data: {
          seo: {
            description:
              'Détail d\'un bien immobilier vérifié par Kaikun 360 : prix, localisation, caractéristiques et demande de visite en ligne.',
            type: 'product',
          },
        },
      },
      {
        // Univers Nuitées (F2.3) : page vitrine + fiche détaillée d'une nuitée.
        path: 'nuitees',
        loadComponent: () =>
          import('./features/stay/stay-list/stay-list-page').then(
            (m) => m.StayListPageComponent,
          ),
        title: 'Nuitées & séjours — Kaikun 360',
        data: {
          seo: {
            description:
              'Appartements et maisons meublés à la nuitée au Sénégal : réservation ferme en ligne, paiement sécurisé, logements contrôlés par nos agents.',
          },
        },
      },
      {
        path: 'nuitees/:id',
        loadComponent: () =>
          import('./features/stay/stay-detail/stay-detail-page').then(
            (m) => m.StayDetailPageComponent,
          ),
        title: 'Nuitée — Kaikun 360',
        data: {
          seo: {
            description:
              'Logement meublé à la nuitée au Sénégal : tarif, équipements, conditions d\'arrivée et réservation immédiate sur Kaikun 360.',
            type: 'product',
          },
        },
      },
      {
        // Univers Tourisme (F2.4) : page vitrine + fiche détaillée d'une expérience.
        path: 'tourisme',
        loadComponent: () =>
          import('./features/explore/experience-list/experience-list-page').then(
            (m) => m.ExperienceListPageComponent,
          ),
        title: 'Tourisme & expériences — Kaikun 360',
        data: {
          seo: {
            description:
              'Circuits, excursions et expériences au Sénégal proposés par des prestataires vérifiés : Saint-Louis, Sine Saloum, Casamance, île de Gorée.',
          },
        },
      },
      {
        path: 'tourisme/:id',
        loadComponent: () =>
          import('./features/explore/experience-detail/experience-detail-page').then(
            (m) => m.ExperienceDetailPageComponent,
          ),
        title: 'Expérience — Kaikun 360',
        data: {
          seo: {
            description:
              'Programme, durée, inclusions et tarif d\'une expérience touristique au Sénégal, réservable en ligne sur Kaikun 360.',
            type: 'product',
          },
        },
      },
      {
        // Univers Transport (F2.4) : page vitrine + fiche détaillée d'un véhicule.
        path: 'transport',
        loadComponent: () =>
          import('./features/mobility/vehicle-list/vehicle-list-page').then(
            (m) => m.VehicleListPageComponent,
          ),
        title: 'Transport & location — Kaikun 360',
        data: {
          seo: {
            description:
              'Location de véhicules au Sénégal, avec ou sans chauffeur : berlines, 4×4 et minibus contrôlés, réservation et paiement en ligne.',
          },
        },
      },
      {
        path: 'transport/:id',
        loadComponent: () =>
          import('./features/mobility/vehicle-detail/vehicle-detail-page').then(
            (m) => m.VehicleDetailPageComponent,
          ),
        title: 'Véhicule — Kaikun 360',
        data: {
          seo: {
            description:
              'Fiche d\'un véhicule de location au Sénégal : tarif journalier, capacité, conformité du dossier et réservation en ligne.',
            type: 'product',
          },
        },
      },
      {
        // Univers Mobilité (F2.4) : vitrine seule (pas de fiche côté backend).
        path: 'mobilite',
        loadComponent: () =>
          import('./features/mobility/mobility-list/mobility-list-page').then(
            (m) => m.MobilityListPageComponent,
          ),
        title: 'Mobilité, navettes & transferts — Kaikun 360',
        data: {
          seo: {
            description:
              'Navettes, transferts aéroport et départs programmés entre les villes du Sénégal : places réservables à l\'unité, horaires publiés.',
          },
        },
      },
      {
        // F8.10 — fiche d'un départ programmé. Elle n'existait pas : l'univers
        // Mobilité s'arrêtait au catalogue, et réserver une place passait
        // forcément par un conseiller sur WhatsApp.
        path: 'mobilite/:id',
        loadComponent: () =>
          import('./features/mobility/trip-detail/trip-detail-page').then(
            (m) => m.TripDetailPageComponent,
          ),
        title: 'Départ — Kaikun 360',
        data: {
          seo: {
            description:
              'Départ programmé au Sénégal : trajet, date, horaire, prix par place et nombre de places restantes. Réservation immédiate.',
            type: 'product',
          },
        },
      },
      {
        // Univers Construction (F2.5) : page de conversion + simulateur de budget.
        path: 'construction',
        loadComponent: () =>
          import('./features/build/construction-page/construction-page').then(
            (m) => m.ConstructionPageComponent,
          ),
        title: 'Construction & simulateur de budget — Kaikun 360',
        data: {
          seo: {
            description:
              'Construisez au Sénégal depuis l\'étranger : simulateur de budget gratuit, devis par lot, suivi de chantier photo et jalons de paiement.',
          },
        },
      },
      {
        // Univers Gestion locative (F2.5) : page de conversion.
        path: 'gestion-locative',
        loadComponent: () =>
          import('./features/manage/manage-page/manage-page').then(
            (m) => m.ManagePageComponent,
          ),
        title: 'Gestion locative — Kaikun 360',
        data: {
          seo: {
            description:
              'Confiez la gestion de votre bien au Sénégal : recherche de locataire, encaissement des loyers, entretien et rapport mensuel détaillé.',
          },
        },
      },
      {
        // Univers Diaspora (F2.5) : page de conversion (protocole de confiance).
        path: 'diaspora',
        loadComponent: () =>
          import('./features/diaspora/diaspora-page/diaspora-page').then(
            (m) => m.DiasporaPageComponent,
          ),
        title: 'Diaspora — projets pilotés à distance — Kaikun 360',
        data: {
          seo: {
            description:
              'Sénégalais de l\'étranger : achetez, construisez et gérez à distance avec un protocole de confiance — agent dédié, preuves photo, paiements jalonnés.',
          },
        },
      },
      {
        // Univers Team building (F2.5) : page de conversion.
        path: 'team-building',
        loadComponent: () =>
          import('./features/team-building/team-building-page/team-building-page').then(
            (m) => m.TeamBuildingPageComponent,
          ),
        title: 'Team building & séminaires — Kaikun 360',
        data: {
          seo: {
            description:
              'Séminaires et team building d\'entreprise au Sénégal : lieu, hébergement, transport et activités organisés de bout en bout, sur devis.',
          },
        },
      },
      {
        // Kaikun Pro (F2.5) : page de conversion prestataires/entreprises.
        path: 'pro',
        loadComponent: () =>
          import('./features/pro/pro-page/pro-page').then((m) => m.ProPageComponent),
        title: 'Kaikun Pro — devenez prestataire vérifié — Kaikun 360',
        data: {
          seo: {
            description:
              'Rejoignez le réseau de prestataires vérifiés Kaikun 360 : missions qualifiées, paiements sécurisés et visibilité auprès de la diaspora.',
          },
        },
      },
      {
        // Inscription prestataire dédiée (F2.7) : formulaire d'adhésion marketplace.
        path: 'pro/inscription',
        loadComponent: () =>
          import('./features/pro/provider-registration/provider-registration-page').then(
            (m) => m.ProviderRegistrationPageComponent,
          ),
        title: 'Devenir prestataire — Kaikun 360',
        data: {
          seo: {
            description:
              'Inscrivez votre entreprise ou votre activité sur Kaikun 360 : dossier en ligne, vérification par nos agents, puis accès aux missions.',
          },
        },
      },
      {
        // Réponse à un devis (F2.7) : consultation + acceptation/refus.
        // On y arrive par un lien reçu en notification (auth requise).
        // ⚠️ AUCUN `seo` : la page est publique par son chemin mais son contenu
        // est celui d'un devis nominatif. Elle reste donc hors index.
        path: 'devis/:id',
        loadComponent: () =>
          import('./features/quote/quote-detail/quote-detail-page').then(
            (m) => m.QuoteDetailPageComponent,
          ),
        title: 'Votre devis — Kaikun 360',
      },
      {
        // Foire aux questions (F2.8) : contenu éditorial servi par GET /faqs.
        path: 'faqs',
        loadComponent: () =>
          import('./features/content/faq/faq-page').then((m) => m.FaqPageComponent),
        title: 'Foire aux questions — Kaikun 360',
        data: {
          // Fermeture d'accès (2026-08-14) : reste accessible même plateforme fermée.
          gateExempt: true,
          seo: {
            description:
              'Réponses aux questions fréquentes sur Kaikun 360 : vérification des biens, paiements, réservations, gestion locative et suivi de chantier.',
          },
        },
      },
      {
        // Page Contact (F2.8) : coordonnées + WhatsApp, sans formulaire.
        path: 'contact',
        loadComponent: () =>
          import('./features/content/contact/contact-page').then((m) => m.ContactPageComponent),
        title: 'Contact — Kaikun 360',
        data: {
          // Fermeture d'accès (2026-08-14) : reste accessible même plateforme fermée.
          gateExempt: true,
          seo: {
            description:
              'Contactez l\'équipe Kaikun 360 à Dakar : téléphone, e-mail et WhatsApp. Une réponse sous 24 h ouvrées pour tout projet au Sénégal.',
          },
        },
      },
      {
        // Liste d'attente avant ouverture officielle (2026-08-14). Détachée de
        // la page statique du client sur le domaine public : route interne,
        // pas encore liée à la navigation.
        path: 'liste-attente',
        loadComponent: () =>
          import('./features/content/waitlist-page/waitlist-page').then(
            (m) => m.WaitlistPageComponent,
          ),
        title: "Liste d'attente — Kaikun 360",
        data: {
          // Elle-même : sinon on redirigerait /liste-attente vers /liste-attente.
          gateExempt: true,
          seo: {
            description:
              "Propriétaire, prestataire, client, entreprise ou diaspora : inscrivez-vous à la liste d'attente Kaikun 360 avant l'ouverture officielle au Sénégal.",
          },
        },
      },
      {
        // F8.6 — Retour de PayTech. PUBLIQUES et hors espace client, à dessein :
        // le client revient d'un autre domaine, parfois longtemps après ; une
        // garde de rôle le renverrait vers la connexion juste après avoir payé.
        // ⚠️ Ces pages ne prouvent RIEN — seul l'IPN signé confirme un
        // encaissement. Elles n'affichent aucune donnée de réservation.
        path: 'paiement/succes',
        data: {
          succeeded: true,
          // Publique, mais sans aucun intérêt pour un moteur — et un résultat
          // « Paiement reçu » dans Google serait inquiétant autant qu'inutile.
          seo: {
            description: 'Confirmation de retour de paiement Kaikun 360.',
            index: false,
          },
        },
        loadComponent: () =>
          import('./features/content/payment-return/payment-return-page').then(
            (m) => m.PaymentReturnPageComponent,
          ),
        title: 'Paiement reçu — Kaikun 360',
      },
      {
        path: 'paiement/annule',
        data: {
          succeeded: false,
          seo: {
            description: 'Retour de paiement interrompu — Kaikun 360.',
            index: false,
          },
        },
        loadComponent: () =>
          import('./features/content/payment-return/payment-return-page').then(
            (m) => m.PaymentReturnPageComponent,
          ),
        title: 'Paiement interrompu — Kaikun 360',
      },
      {
        // Pages de contenu éditorial adressées par slug (F2.8) : À propos,
        // mentions légales, CGU, politique de confidentialité… Le titre de
        // l'onglet est affiné par le composant une fois la page chargée.
        path: 'pages/:slug',
        loadComponent: () =>
          import('./features/content/content-page/content-page').then(
            (m) => m.ContentPageComponent,
          ),
        title: 'Kaikun 360',
        data: {
          // Fermeture d'accès (2026-08-14) : CGU/confidentialité doivent rester lisibles.
          gateExempt: true,
          seo: {
            description:
              'Page d\'information Kaikun 360 : conditions, politiques et mentions encadrant l\'usage de la plateforme.',
            type: 'article',
          },
        },
      },
      {
        // F10.1.a — LA PAGE D'ERREUR, cible de `errorInterceptor` depuis F0.
        //
        // ⚠️ Elle n'existait pas. L'intercepteur y renvoyait pourtant à chaque
        // réponse 0 ou 5xx : au rendu serveur cela levait `NG04002: 'erreur'`,
        // et au navigateur le routeur annulait la navigation — la personne
        // restait sur sa page, sans un mot, en croyant que son clic n'avait rien
        // fait. Défaut silencieux vieux de toute la phase F.
        path: 'erreur',
        loadComponent: () =>
          import('./features/content/error-page/error-page').then((m) => m.ErrorPageComponent),
        title: 'Service indisponible — Kaikun 360',
        data: {
          kind: 'serveur',
          seo: {
            description: 'Une difficulté technique empêche l\'affichage de cette page.',
            // Une page d'erreur indexée abîme la réputation du domaine entier.
            index: false,
          },
        },
      },
      {
        // F10.1.a — LE 404, jumeau du précédent : le site n'avait AUCUNE route
        // attrape-tout, si bien qu'une adresse inconnue (lien périmé partagé sur
        // WhatsApp, annonce retirée, faute de frappe) produisait exactement le
        // même silence.
        //
        // ⚠️ **DOIT RESTER LA TOUTE DERNIÈRE ROUTE DU FICHIER** : `**` accepte
        // tout, une route déclarée après elle serait inatteignable.
        path: '**',
        loadComponent: () =>
          import('./features/content/error-page/error-page').then((m) => m.ErrorPageComponent),
        title: 'Page introuvable — Kaikun 360',
        data: {
          kind: 'introuvable',
          seo: {
            description: 'Cette page n\'existe pas ou n\'est plus en ligne.',
            index: false,
          },
        },
      },
    ],
  },
];
