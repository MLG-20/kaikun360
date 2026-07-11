# Kaikun 360 — Plan de développement (Backend Laravel + Frontend Angular)

> Document de suivi de développement, dérivé du cahier des charges technique Kaikun 360 v2.0.
> Périmètre de ce document : **API Laravel** et **Frontend web Angular** uniquement. L'application mobile Android est volontairement exclue et sera traitée dans un document séparé.
> Chaque phase liste l'ensemble des tâches à réaliser pour fermer le périmètre fonctionnel correspondant du cahier des charges. Aucune tâche n'est codée ici — ce fichier sert de feuille de route à cocher au fur et à mesure du développement avec Claude dans VS Code.

---

## Sommaire

- [Rappel du périmètre fonctionnel](#rappel-du-périmètre-fonctionnel)
- [BACKEND — Laravel](#backend--laravel)
  - [Phase B0 — Initialisation et socle technique](#phase-b0--initialisation-et-socle-technique)
  - [Phase B1 — Authentification, utilisateurs et rôles (module Core)](#phase-b1--authentification-utilisateurs-et-rôles-module-core)
  - [Phase B2 — Module Immo (achat / vente / location mensuelle)](#phase-b2--module-immo-achat--vente--location-mensuelle)
  - [Phase B3 — Module Stay (nuitées / hébergements courte durée)](#phase-b3--module-stay-nuitées--hébergements-courte-durée)
  - [Phase B4 — Module Manage (gestion locative)](#phase-b4--module-manage-gestion-locative)
  - [Phase B5 — Module Build (construction / rénovation / devis)](#phase-b5--module-build-construction--rénovation--devis)
  - [Phase B6 — Module Explore (tourisme et expériences)](#phase-b6--module-explore-tourisme-et-expériences)
  - [Phase B7 — Module Mobility (transport et mobilité)](#phase-b7--module-mobility-transport-et-mobilité)
  - [Phase B8 — Module Diaspora](#phase-b8--module-diaspora)
  - [Phase B9 — Module Team Building](#phase-b9--module-team-building)
  - [Phase B10 — Module Pro (marketplace prestataires)](#phase-b10--module-pro-marketplace-prestataires)
  - [Phase B11 — Requests, Quotes, Bookings (couche transversale)](#phase-b11--requests-quotes-bookings-couche-transversale)
  - [Phase B12 — Médias, avis et qualité](#phase-b12--médias-avis-et-qualité)
  - [Phase B13 — Back-office / Admin (API)](#phase-b13--back-office--admin-api)
  - [Phase B14 — Paiement avec PayTech](#phase-b14--paiement-avec-paytech)
  - [Phase B15 — Sécurité, conformité et journalisation](#phase-b15--sécurité-conformité-et-journalisation)
  - [Phase B16 — Notifications et communication](#phase-b16--notifications-et-communication)
  - [Phase B17 — Durcissement et performance](#phase-b17--durcissement-et-performance)
- [FRONTEND — Angular](#frontend--angular)
  - [Phase F0 — Initialisation et socle technique](#phase-f0--initialisation-et-socle-technique)
  - [Phase F1 — Authentification et onboarding](#phase-f1--authentification-et-onboarding)
  - [Phase F2 — Pages publiques (PublicModule)](#phase-f2--pages-publiques-publicmodule)
  - [Phase F3 — Espace client (ClientSpaceModule)](#phase-f3--espace-client-clientspacemodule)
  - [Phase F4 — Espace propriétaire (OwnerSpaceModule)](#phase-f4--espace-propriétaire-ownerspacemodule)
  - [Phase F5 — Espace prestataire (ProviderSpaceModule)](#phase-f5--espace-prestataire-providerspacemodule)
  - [Phase F6 — Espace entreprise (EnterpriseSpaceModule)](#phase-f6--espace-entreprise-enterprisespacemodule)
  - [Phase F7 — Back-office (AdminModule)](#phase-f7--back-office-adminmodule)
  - [Phase F8 — État global, design system et finitions](#phase-f8--état-global-design-system-et-finitions)
  - [Phase F9 — SEO, performance et accessibilité](#phase-f9--seo-performance-et-accessibilité)
- [Critères d'acceptation transverses](#critères-dacceptation-transverses)
- [Hors développement (à suivre en parallèle)](#hors-développement-à-suivre-en-parallèle)

---

## Rappel du périmètre fonctionnel

9 univers à couvrir : **Immo, Stay, Manage, Build, Explore, Mobility, Diaspora, Team Building, Pro**.
5 espaces utilisateurs à séparer strictement : **Client, Propriétaire, Prestataire, Entreprise/Diaspora, Back-office Kaikun**.
8 rôles : **Visiteur, Client, Propriétaire, Prestataire, Entreprise, Agent Kaikun, Admin, Super Admin**.

---

## BACKEND — Laravel

### Phase B0 — Initialisation et socle technique

- [x] Initialiser le projet Laravel (dernière version stable). — Laravel 13.17.0 dans `backend/`.
- [x] Configurer l'environnement : `.env` (DB, Redis, mail, queue, filesystem).
- [x] Choisir et configurer la base de données (MySQL/MariaDB ou PostgreSQL). — MySQL 8, base `kaikun360`.
- [x] Configurer Redis (cache + sessions + queue).
- [ ] Configurer le driver de queue (database ou Redis) et un worker supervisé. — _Driver Redis configuré ; worker supervisé (Supervisor/systemd) reporté au déploiement._
- [x] Mettre en place la structure de dossiers par domaine métier (Core, Immo, Stay, Manage, Build, Explore, Mobility, Diaspora, TeamBuilding, Pro, Admin). — `app/Modules/`.
- [x] Configurer le versionnement de l'API (`/api/v1/...`).
- [x] Mettre en place le format de réponse JSON standard (enveloppe data/meta/links pour les listes paginées). — `App\Support\ApiResponse`.
- [x] Configurer CORS pour autoriser uniquement les domaines Kaikun officiels. — `config/cors.php` + `CORS_ALLOWED_ORIGINS`.
- [x] Configurer le rate limiting global de l'API. — 60 req/min (`throttle:api`).
- [x] Mettre en place les enums Laravel pour tous les statuts métier (bien, demande, réservation, paiement).
- [x] Mettre en place le format d'erreur standard (validation 422, autorisation 403, non trouvé 404).
- [x] Installer et configurer Laravel Sanctum.
- [x] Installer et configurer Spatie Laravel-Permission (rôles et permissions).
- [x] Installer et configurer un package d'audit log (ex. Spatie Activitylog).
- [x] Mettre en place les tests automatisés de base (Pest ou PHPUnit) et la structure de tests par module. — PHPUnit, base `kaikun360_test`.
- [x] Initialiser le dépôt Git et la stratégie de branches. — Dépôt local sur `main` (GitHub à créer plus tard).

### Phase B1 — Authentification, utilisateurs et rôles (module Core)

- [x] Migration `users` (id, nom, téléphone, email, rôle, ville, statut, date_creation). — _rôle géré par Spatie (pas de colonne)._
- [x] Migration `profiles` (user_id, type_profil, documents, vérification, préférences). — _documents dans table dédiée `user_documents`._
- [x] Modèles Eloquent `User` et `Profile` avec relations (hasOne/belongsTo).
- [x] Définir les 8 rôles dans Spatie Permission (Visiteur, Client, Propriétaire, Prestataire, Entreprise, Agent Kaikun, Admin, Super Admin) et leurs permissions. — _seeder + matrice initiale._
- [x] Endpoint `POST /auth/register` avec choix de profil (client, propriétaire, prestataire, entreprise, diaspora).
- [x] Endpoint `POST /auth/login` (email ou téléphone).
- [x] Endpoint `POST /auth/verify` (vérification code SMS/email). — _SMS réel à brancher en B16 ; codes loggés en dev._
- [x] Endpoint `POST /auth/logout`.
- [x] Endpoint `GET /users/me` (profil utilisateur connecté).
- [x] Endpoint `PATCH /users/me` (mise à jour du profil).
- [x] Endpoint `POST /users/me/documents` (dépôt de pièce d'identité / documents justificatifs). — _disque privé + URL signée temporaire._
- [x] Form Requests de validation pour inscription et mise à jour de profil.
- [x] Policies de base : un utilisateur ne peut lire/modifier que son propre profil (sauf Admin/Super Admin).
- [x] Logique de récupération de compte (mot de passe oublié / téléphone oublié).
- [x] Tests d'autorisation : vérifier qu'aucun rôle n'accède aux données d'un autre utilisateur sans permission explicite.

### Phase B2 — Module Immo (achat / vente / location mensuelle)

- [x] Migration `properties` (id, owner_id, type, titre, ville, adresse, prix, statut, vérification + index ville/statut/type/prix). — _localisation via référentiel region/department/commune._
- [x] Migration `property_documents` (property_id, type_document, fichier, statut_validation).
- [x] Modèles `Property` et `PropertyDocument` avec relations (belongsTo owner, hasMany documents, morphMany media). — _média (morphMany) en B12._
- [x] Statut par défaut `en_attente_validation` à la création d'un bien.
- [x] Endpoint `GET /properties` — catalogue filtrable (ville, région, département, commune, zone touristique, type, prix, statut de vérification).
- [x] Endpoint `POST /properties` — dépôt de bien par un propriétaire.
- [x] Endpoint `GET /properties/{id}` — détail d'un bien.
- [x] Endpoint `PATCH /properties/{id}` — mise à jour (propriétaire ou admin uniquement).
- [x] Endpoint `POST /properties/{id}/documents` — ajout de documents liés au bien.
- [x] Policy : un propriétaire ne modifie/voit que ses propres biens en gestion privée ; le catalogue public reste filtré aux biens validés.
- [x] Logique de favoris (table pivot ou relation many-to-many user/property).
- [x] Logique de comparaison de biens (endpoint ou paramètre de requête multi-ID).
- [x] Event `PropertyCreated` → notification agents pour mise en file de validation.
- [x] Event `PropertyValidated` → publication effective dans le catalogue.
- [x] Tests : un visiteur ne voit jamais un bien non validé ; un propriétaire ne modifie pas le bien d'un autre.

### Phase B3 — Module Stay (nuitées / hébergements courte durée)

- [x] Migration `stays` (property_id, prix_nuit, disponibilité, caution, règles, capacité).
- [x] Modèle `Stay` (belongsTo Property, hasMany Booking). — _bookings polymorphes._
- [x] Endpoint `GET /stays` — catalogue des hébergements courte durée.
- [x] Endpoint `GET /stays/{id}/availability` — calendrier de disponibilité.
- [x] Endpoint `POST /stays/{id}/bookings` — réservation d'une nuitée.
- [x] Logique de gestion de caution (montant, statut de retenue/restitution). — _montant capturé ; retenue/restitution (remboursement PayTech) → B11/B14._
- [ ] Logique de check-in / check-out et statut de ménage (rattachée au back-office, section B13). — _reporté à B13._
- [x] Validation des dates (pas de double réservation sur un même créneau).
- [x] Tests : impossibilité de réserver un créneau déjà occupé.

### Phase B4 — Module Manage (gestion locative)

- [x] Tables/relations pour contrats de gestion locative (mandat, durée, taux de commission).
- [x] Table loyers (échéances, statut payé/impayé, lien au bien et au locataire).
- [x] Table incidents (signalement, statut de traitement, lien au bien).
- [x] Table dépenses liées à un bien (maintenance, réparations).
- [x] Logique de reversement au propriétaire (montant, date, justificatif).
- [x] Endpoints CRUD pour mandats, loyers, incidents, dépenses, reversements.
- [x] Endpoint de tableau de bord propriétaire (agrégation loyers/incidents/reversements d'un propriétaire).
- [x] Génération de rapport mensuel (données structurées consommées par le frontend ; export PDF reporté).
- [x] Policy : un propriétaire ne voit que les données de gestion locative de ses propres biens.

### Phase B5 — Module Build (construction / rénovation / devis)

- [x] Migration `construction_requests` (client_id, budget, ville, surface, objectif, niveau_finition, statut).
- [x] Modèle `ConstructionRequest` (belongsTo client, hasMany Report ; hasMany Quote rattaché en B11).
- [x] Logique de simulateur (calcul indicatif budget/surface/niveau de finition — règles métier dans un Service dédié).
- [x] Endpoint `POST /construction-requests` — demande détaillée de devis.
- [x] Endpoint `GET /construction-requests/{id}/reports` — rapports photo/vidéo de suivi de chantier.
- [x] Migration et modèle `reports` (project_id, type, photos, vidéo, commentaire, date) — relation polymorphique (Construction ou Diaspora).
- [x] Logique de jalons de chantier (étapes, statut, date prévisionnelle/réelle).
- [x] Policy : un client ne voit que ses propres demandes de construction et leurs rapports.

### Phase B6 — Module Explore (tourisme et expériences)

- [x] Migration `tourism_experiences` (titre, destination, durée, inclusions, prix, capacité, prestataire_id).
- [x] Modèle `TourismExperience` (belongsTo provider, hasMany Booking).
- [x] Endpoint `GET /experiences` — catalogue de circuits et expériences.
- [x] Endpoint `POST /experiences` — publication par un prestataire (statut en attente de validation).
- [x] Logique de panier groupe / capacités par circuit (places restantes).
- [x] Gestion des inclusions (restauration, guide) en champ structuré.
- [x] Logique d'annulation de réservation d'expérience (statut annulé, délai d'éligibilité au remboursement ; déclenchement du remboursement via PayTech câblé en B14).
- [x] Policy : seuls les prestataires validés peuvent publier une expérience.

### Phase B7 — Module Mobility (transport et mobilité)

- [x] Migration `vehicles` (provider_id, type, marque, capacité, prix_jour, chauffeur, assurance, statut).
- [x] Migration `mobility_services` (type, départ, destination, capacité, prix, prestataire_id, statut).
- [x] Modèles `Vehicle` et `MobilityService` avec relations (belongsTo provider, morphMany Booking ; média en B12).
- [x] Distinction technique claire entre catégories : voiture particulière, voiture touristique, navette aéroportuaire (AIBD), bus, minibus, 4x4, pirogue, chauffeur.
- [x] Endpoint `GET /vehicles`, `POST /vehicles`, `PATCH /vehicles/{id}`.
- [x] Endpoint `GET /mobility-services` — recherche par type/ville/dates.
- [x] Champs obligatoires de vérification pour les pirogues (capacité, gilets, conditions météo, conformité prestataire) bloquant la validation si absents.
- [x] Champs obligatoires pour le transport motorisé (assurance, capacité, identité chauffeur).
- [x] Event `VehicleCreated` → file de validation ; `VehicleValidated` → apparition dans la recherche.
- [x] Service `CommissionCalculator` déclenché à chaque réservation de mobilité.
- [x] Logique de caution sur les réservations de véhicule (montant, statut de retenue/restitution ; remboursement via PayTech en cas d'annulation conforme câblé en B14).
- [x] Policy : un prestataire ne modifie que ses propres véhicules/services.

### Phase B8 — Module Diaspora

- [x] Migration `diaspora_projects` (client_id, type_projet, pays_residence, budget, statut, agent_id).
- [x] Modèle `DiasporaProject` (belongsTo client, belongsTo agent, hasMany Report — relation polymorphique avec Build).
- [x] Endpoint de création et suivi de projet diaspora (achat, construction, gestion locative).
- [x] Affectation d'un agent dédié au dossier (champ agent_id + logique d'attribution).
- [x] Endpoint d'ajout de rapports (photo/vidéo) liés au projet.
- [x] Priorisation des dossiers à forte valeur (champ priorité, visible côté back-office).
- [x] Policy : un client diaspora ne voit que ses propres projets ; l'agent assigné y a accès en lecture/écriture.

### Phase B9 — Module Team Building

- [x] Migration `team_building_requests` (entreprise, participants, ville, dates, budget, besoins).
- [x] Modèle `TeamBuildingRequest` (belongsTo User entreprise, hasMany Quote — devis scopé module, généralisé en B11).
- [x] Endpoint `POST /team-building-requests` — demande de pack groupe (participants, lieu, durée, budget, activités, transport, hébergement).
- [x] Logique de composition de devis multi-prestataires (lieu + hébergement + restauration + activité + mobilité + animation), agrégeant plusieurs modules (Manage/Stay, Explore, Mobility).
- [x] Event `TeamBuildingRequestCreated` → file d'attente admin dédiée.
- [x] Event `QuoteSent` / `QuoteAccepted` → déclenchement du suivi opérationnel multi-prestataires.
- [x] Policy : une entreprise ne voit que ses propres demandes et devis.

### Phase B10 — Module Pro (marketplace prestataires)

- [x] Logique d'inscription prestataire avec documents de certification.
- [x] Statuts de validation prestataire (en attente, validé, refusé, suspendu).
- [x] Endpoint de gestion des missions affectées à un prestataire.
- [~] Système de notation/avis spécifique aux prestataires (champs `rating_avg`/`rating_count` prêts ; remplissage par le module reviews en B12).
- [x] Logique de calcul de commission par mission/prestataire (réutilise `CommissionCalculator`).
- [x] Charte qualité : champs de sanction/avertissement liés au profil prestataire.
- [x] Policy : un prestataire non validé ne peut publier aucun service en public.

### Phase B11 — Requests, Quotes, Bookings (couche transversale)

- [x] Migration `requests` (user_id, type_service, message, budget, ville, statut, priorité).
- [x] Migration `quotes` (request_id, montant, détails, validité, statut).
- [x] Migration `bookings` (user_id, item_type, item_id, dates, montant, statut) — relation polymorphique vers Property/Stay/Vehicle/Experience.
- [x] Modèles `Request`, `Quote`, `Booking` avec relations complètes.
- [x] Statut d'annulation sur `bookings` (annulé_client, annulé_prestataire, annulé_admin) avec horodatage, distinct du statut de paiement.
- [x] State machine stricte des statuts de demande : reçu → vérification → visite → devis → négociation → clôturé (transitions validées côté backend uniquement).
- [x] Endpoint `POST /requests` — création d'une demande générique.
- [x] Endpoint `GET /requests/my` — suivi des demandes de l'utilisateur connecté.
- [x] Endpoint `PATCH /requests/{id}/status` — changement de statut (réservé agents/admin).
- [x] Endpoint `GET /bookings/my` — réservations de l'utilisateur connecté.
- [x] Endpoint `GET /quotes/{id}` et `PATCH /quotes/{id}` — consultation/validation d'un devis.
- [x] Event `RequestCreated` → notification des agents disponibles.
- [x] Event `RequestStatusChanged` → Job de notification (push/WhatsApp/email).
- [x] Tests : aucune transition de statut invalide n'est acceptée par l'API.

### Phase B12 — Médias, avis et qualité

- [x] Migration `media` (item_type, item_id, url, type, ordre, statut) — relation polymorphique (properties, vehicles, experiences, etc.).
- [x] Migration `reviews` (user_id, item_type, item_id, note, commentaire, statut) — relation polymorphique.
- [x] Endpoint `POST /media/upload` et `DELETE /media/{id}`.
- [x] Logique de compression et validation des images uploadées.
- [x] Gestion de l'image principale vs galerie secondaire.
- [x] Endpoint de création d'avis, avec statut de modération (en attente / publié / rejeté).
- [ ] Endpoint admin de modération des avis (lié au back-office, section B13).
- [x] Policy : un avis ne peut être laissé que par un utilisateur ayant effectivement consommé le service/bien concerné.

### Phase B13 — Back-office / Admin (API)

- [ ] Endpoint `GET /admin/dashboard` — demandes du jour, revenus estimés, biens en attente, prestataires à valider, alertes, KPI.
- [ ] Endpoint `GET /admin/queue` — file de validation (biens, véhicules, circuits, prestataires).
- [ ] Endpoint `PATCH /admin/validate/{type}/{id}` — validation ou refus générique par type de ressource.
- [ ] Endpoint `GET /admin/users` et `PATCH /admin/users/{id}` — gestion des comptes, rôles, statut, désactivation.
- [ ] Endpoint `GET /admin/reports/export` — export comptable et reporting.
- [ ] Endpoints de paramétrage : villes, catégories, tarifs, commissions, FAQ, contenu des pages.
- [ ] Endpoints de gestion documentaire transverse (mandats, contrats, preuves, pièces prestataires).
- [ ] Endpoints spécifiques nuitées (calendrier global, caution, statut ménage/check-in/check-out) pour vue back-office.
- [ ] Endpoints spécifiques gestion locative (contrats, loyers, incidents, dépenses, reversements) pour vue back-office.
- [ ] Endpoints spécifiques construction (devis, prestataires BTP, jalons, rapports) pour vue back-office.
- [ ] Endpoints spécifiques mobilité (véhicules, chauffeurs, disponibilités, assurances) pour vue back-office.
- [ ] Endpoints spécifiques tourisme (circuits, destinations, guides, capacités groupe) pour vue back-office.
- [ ] Endpoints spécifiques team building (demandes, packages, devis, affectation prestataires) pour vue back-office.
- [ ] Endpoints spécifiques diaspora (dossiers à forte valeur, vérification, reporting, priorités) pour vue back-office.
- [ ] Policies différenciées Agent / Admin / Super Admin selon le tableau de rôles du cahier des charges.

### Phase B14 — Paiement avec PayTech

- [ ] Créer un compte marchand PayTech (env. test/sandbox d'abord, puis demande d'activation production par email à PayTech).
- [ ] Définir l'interface `PaymentProviderInterface` (méthodes : initiate, confirm, refund, status) — même si PayTech est le seul provider actif, l'interface reste en place pour ne jamais coupler le reste du code à un fournisseur précis.
- [ ] Implémenter `PaytechProvider` (initiate via `POST /api/v1/payments` sur `engine-sandbox.pay.tech` puis `engine.pay.tech` en prod, en-tête `Bearer` avec la clé API boutique).
- [ ] Stocker en configuration (jamais en dur dans le code) : clé API PayTech, clé de signature webhook (Signing Key), URL de webhook.
- [ ] Migration `payments` (booking_id, provider, montant, statut, reference, mode) — statut aligné sur les états PayTech (`AUTHORIZED`, `COMPLETED`, `DECLINED`, `CANCELLED`).
- [ ] Endpoint `POST /payments/initiate` — crée l'intention de paiement côté PayTech et retourne l'URL/redirection au frontend.
- [ ] Endpoint `POST /payments/webhook` — réception des notifications PayTech.
- [ ] **Validation obligatoire de la signature du webhook** : vérifier l'en-tête `Signature` (HMAC-SHA256 du corps JSON avec la Signing Key) avant de traiter toute notification — ne jamais faire confiance à un webhook non vérifié.
- [ ] Mapper les statuts PayTech vers les statuts internes `payments`/`bookings` (ex. `COMPLETED` → booking confirmé, `DECLINED`/`CANCELLED` → booking non confirmé).
- [ ] Implémenter le remboursement (`refund`) via PayTech pour les cas de caution à restituer ou d'annulation éligible (Mobility, Explore, Stay).
- [ ] Logique de calcul et d'enregistrement des commissions Kaikun par transaction réussie.
- [ ] Gérer le cas où le montant débité diffère du montant demandé (certains moyens PayTech) : réconciliation explicite, jamais de confirmation automatique sur une simple différence de montant non vérifiée.
- [ ] Endpoint admin de supervision des paiements (liste, statut, recherche par référence) — lié à B13.
- [ ] Tests en environnement sandbox PayTech avant toute bascule en production.
- [ ] Tests : aucun module métier (Bookings, Quotes, Mobility, Explore) ne dépend directement de PayTech, uniquement de `PaymentProviderInterface`.

### Phase B15 — Sécurité, conformité et journalisation

- [ ] Vérification email/téléphone obligatoire avant activation complète d'un compte.
- [ ] Policies sur chaque ressource sensible + scopes Eloquent systématiques par propriétaire.
- [ ] Audit log sur : validation de bien, modification de prix, validation de paiement, suppression de ressource.
- [ ] Stockage des documents sur disque non public + URLs signées temporaires.
- [ ] Statut `en_attente_validation` par défaut sur biens, véhicules, circuits, prestataires.
- [ ] Rate limiting sur les endpoints sensibles (auth, paiement).
- [ ] Politique de confidentialité techniquement reflétée (durée de conservation par type de donnée, anonymisation/suppression sur demande).
- [ ] Revue de sécurité : aucun endpoint ne renvoie de données hors du périmètre autorisé du rôle appelant.

### Phase B16 — Notifications et communication

- [ ] Configurer les canaux de notification Laravel (email, SMS optionnel, push différé pour plus tard avec mobile).
- [ ] Intégration du module WhatsApp click-to-chat contextuel (génération de message prérempli selon page/service).
- [ ] Jobs asynchrones pour l'envoi de notifications (ne jamais bloquer la requête HTTP).
- [ ] Templates de notification par type d'événement (changement de statut, nouveau devis, confirmation de réservation, document à fournir).

### Phase B17 — Durcissement et performance

- [ ] Index de base de données sur les colonnes de filtrage fréquent (ville, statut, type, prix) sur toutes les tables de catalogue.
- [ ] Mise en cache Redis des résultats de recherche/catalogue les plus consultés.
- [ ] Tests de charge sur les endpoints de catalogue et de recherche.
- [ ] Revue complète des Form Requests (aucune donnée non validée ne doit atteindre la couche métier).
- [ ] Documentation technique des endpoints (a minima OpenAPI ou commentaires structurés).

---

## FRONTEND — Angular

### Phase F0 — Initialisation et socle technique

- [ ] Initialiser le projet Angular (dernière version stable, configuration TypeScript stricte).
- [ ] Mettre en place le routing principal avec lazy loading par feature module.
- [ ] Créer le `CoreModule` : `AuthService`, `TokenInterceptor`, `ErrorInterceptor`.
- [ ] Créer le `SharedModule` : composants UI réutilisables (cartes de bien/service, galerie photo, badges de vérification, boutons CTA).
- [ ] Mettre en place le design system Kaikun (couleurs #0348FB et #38A774, typographie, composants de base) conforme à la charte.
- [ ] Définir les modèles TypeScript miroir des API Resources Laravel (Property, Stay, Vehicle, Experience, Request, Quote, Booking, Payment, User, Review, Media).
- [ ] Mettre en place les guards (`AuthGuard`, `RoleGuard`).
- [ ] Mettre en place la gestion centralisée des erreurs HTTP (401 → redirection login, 422 → affichage erreurs de formulaire, 500 → page d'erreur générique).
- [ ] Configurer l'environnement (URLs API par environnement dev/prod).

### Phase F1 — Authentification et onboarding

- [ ] Page d'inscription avec choix de profil (client, propriétaire, prestataire, diaspora, entreprise).
- [ ] Page de connexion (email ou téléphone).
- [ ] Écran de vérification (code SMS/email).
- [ ] Écran de récupération de compte.
- [ ] Gestion du stockage du token (en mémoire/state, jamais en localStorage non sécurisé) et du rafraîchissement de session.

### Phase F2 — Pages publiques (PublicModule)

- [ ] Page Accueil : hero, recherche rapide, présentation des espaces, services prioritaires, éléments de confiance.
- [ ] Page Immobilier : filtres biens (villes, type, prix), vérification, demande de visite.
- [ ] Page Nuitées : calendrier, photos, équipements, prix/nuit, disponibilité.
- [ ] Page Gestion locative : mandats, taux, reporting, maintenance, reversements (page de conversion propriétaire).
- [ ] Page Construction : simulateur, niveaux de finition, rénovation, suivi chantier.
- [ ] Page Tourisme & expériences : destinations, programmes, durée, inclusions, guide, restauration.
- [ ] Page Transport & mobilité : location voiture particulière, voiture touristique, navette AIBD, bus, minibus, 4x4, pirogue.
- [ ] Page Diaspora : vérification, achat, construction, suivi vidéo, gestion locative.
- [ ] Page Team building : packs, lieux, activités, transport, hébergement, restauration.
- [ ] Page Kaikun Pro : conditions, certification, documents, avantages, commissions (page de recrutement prestataires).
- [ ] Page À propos : mission, équipe, ancrage territorial, partenaires.
- [ ] Page FAQ : paiement, vérification, litiges, caution, diaspora, transport.
- [ ] Page Contact : formulaire, WhatsApp, téléphone, email, localisation.
- [ ] Pages légales : CGU, CGV, confidentialité, cookies, conditions de mandat, politique de remboursement.
- [ ] Composant moteur de recherche global (service + ville + budget + dates + profil utilisateur).
- [ ] Composant catalogue filtrable et triable, réutilisé sur toutes les pages d'univers.
- [ ] Composant fiche détaillée (photos, description, localisation, prix, disponibilité, règles, preuves, avis, CTA).
- [ ] Composants de formulaires intelligents : demande client, dépôt de bien, inscription prestataire, demande diaspora, devis team building.
- [ ] Composant simulateur immobilier/construction (budget, ville, surface, objectif, niveau de finition).
- [ ] Composant module WhatsApp contextuel (message prérempli selon page/service).
- [ ] Composant galerie image (image principale, compression côté upload, alt text, statut de validation visible).
- [ ] Composant témoignages et preuves (labels de vérification, documents visibles selon niveau d'autorisation).
- [ ] Mise en place d'Angular Universal (SSR) sur l'ensemble des pages publiques.

### Phase F3 — Espace client (ClientSpaceModule)

- [ ] Écran Favoris (biens et services sauvegardés).
- [ ] Écran Mes demandes (suivi visuel du statut : reçu, en vérification, devis, confirmé, clôturé).
- [ ] Écran Réservations (nuitées, mobilité, expériences).
- [ ] Écran Messages (conversation avec support Kaikun ou prestataire affecté).
- [ ] Écran Notifications (mises à jour demande, réservation, document, paiement).
- [ ] Écran Profil (identité, téléphone, documents, préférences, sécurité).
- [ ] Connexion de tous les formulaires de demande aux endpoints `requests`/`bookings` du backend.

### Phase F4 — Espace propriétaire (OwnerSpaceModule)

- [ ] Formulaire de dépôt de bien (photos, localisation, type de location, documents).
- [ ] Choix du mode de location (mensuelle, nuitées, formule mixte).
- [ ] Tableau de bord propriétaire : demandes, visites, réservations, loyers, incidents.
- [ ] Écran de suivi des reversements et rapports mensuels de gestion locative.
- [ ] Écran de gestion des documents propres au propriétaire.

### Phase F5 — Espace prestataire (ProviderSpaceModule)

- [ ] Formulaire de dépôt de service (voiture, pirogue, circuit, BTP, guide, hébergement) avec documents de certification.
- [ ] Écran de gestion des disponibilités.
- [ ] Écran des missions reçues et de leur statut.
- [ ] Écran des avis reçus et de la notation.
- [ ] Écran de suivi des revenus et commissions.

### Phase F6 — Espace entreprise (EnterpriseSpaceModule)

- [ ] Formulaire de demande de team building (participants, ville, dates, budget, activités, besoin transport/hébergement).
- [ ] Écran de suivi des devis composés par l'admin.
- [ ] Écran d'historique des commandes/demandes groupe.

### Phase F7 — Back-office (AdminModule)

- [ ] Écran Tableau de bord (demandes du jour, revenus estimés, biens en attente, prestataires à valider, alertes, KPI).
- [ ] Écran Utilisateurs (liste, rôles, statut, documents, historique, désactivation).
- [ ] Écran Biens immobiliers (créer, modifier, valider, publier, archiver, attribuer).
- [ ] Écran Nuitées (calendrier, disponibilité, caution, ménage, check-in/check-out).
- [ ] Écran Gestion locative (contrats, loyers, incidents, dépenses, reversements, rapport mensuel).
- [ ] Écran Construction (devis, prestataires BTP, jalons chantier, rapports photo/vidéo).
- [ ] Écran Mobilité (véhicules, chauffeurs, pirogues, bus, disponibilités, assurances, capacités).
- [ ] Écran Tourisme (circuits, destinations, programmes, guides, restaurants, capacités groupes).
- [ ] Écran Team building (demandes entreprises, packages, devis, programme, affectation prestataires).
- [ ] Écran Diaspora (dossiers à forte valeur, vérification, suivi chantier, reporting, priorités).
- [ ] Écran Paiements (acomptes, soldes, commissions, statuts, remboursements, export comptable).
- [ ] Écran Documents (mandats, contrats, preuves, pièces, rapports, pièces prestataires).
- [ ] Écran Avis et qualité (modération avis, notation prestataire, incidents, sanctions).
- [ ] Écran Paramètres (villes, catégories, tarifs, commissions, pages, FAQ, notifications).
- [ ] Guards spécifiques par rôle (Agent / Admin / Super Admin) sur chaque écran sensible.

### Phase F8 — État global, design system et finitions

- [ ] Mettre en place la gestion d'état transverse (Signals ou store léger) pour : utilisateur connecté, panier de demande en cours, filtres de recherche actifs, compteur de notifications non lues.
- [ ] Harmoniser tous les CTA selon la règle « un CTA unique par service ».
- [ ] Vérifier la cohérence visuelle sur l'ensemble des pages (couleurs, espacements, composants partagés).
- [ ] Tests d'intégration sur les parcours critiques (recherche → demande → suivi ; dépôt de bien → validation → publication).

### Phase F9 — SEO, performance et accessibilité

- [ ] Vérifier le rendu SSR de toutes les pages publiques (balises meta, titres, données structurées si pertinent).
- [ ] Optimiser le chargement (lazy loading images, code-splitting par module déjà couvert par le routing).
- [ ] Vérifier la responsivité mobile-first sur l'ensemble des écrans.
- [ ] Test de performance sur connexion moyenne (objectif de chargement raisonnable sur réseau mobile sénégalais standard).
- [ ] Vérification d'accessibilité de base (contrastes, labels de formulaire, navigation clavier).

---

## Critères d'acceptation transverses

- [ ] Un visiteur comprend Kaikun 360 en moins de 5 secondes sur la page d'accueil et voit les CTA clients/offreurs.
- [ ] Le catalogue se filtre par service, ville, prix, type et statut de vérification.
- [ ] Un client peut créer un compte, faire une demande et suivre son statut de bout en bout.
- [ ] Un propriétaire peut déposer un bien avec photos et documents.
- [ ] Un prestataire peut proposer véhicule, circuit, pirogue, prestation BTP ou guide.
- [ ] La location de voiture particulière, voiture touristique et navette aéroportuaire sont des catégories distinctes et identifiables.
- [ ] Un dossier diaspora peut être créé, suivi et enrichi de rapports.
- [ ] Une entreprise peut demander un pack groupe avec participants, lieu, dates et budget.
- [ ] Un admin peut valider, modifier, publier, refuser et suivre toutes les demandes depuis le back-office.
- [ ] Chaque rôle ne voit que ses données autorisées (vérifié par des tests d'autorisation systématiques).
- [ ] Le site est responsive, rapide et utilisable sur connexion moyenne.

---

## Hors développement (à suivre en parallèle)

- [ ] Nom de domaine (kaikun360.sn ou équivalent disponible).
- [ ] Hébergement VPS définitif (Laravel + Angular + Redis + workers).
- [ ] Validation juridique : CGU/CGV, mandats, contrats, conformité données personnelles.
- [ ] Vérification réglementaire transport (autorisations, assurances, agréments).
- [ ] Ouverture d'un compte marchand ou contractualisation avec un agrégateur de paiement (Wave, Orange Money).
- [ ] Séparation comptable des fonds clients, commissions et reversements.
- [ ] Production de contenu réel : photos des biens, véhicules, destinations, équipe.

---

*Document de suivi — dérivé du cahier des charges technique Kaikun 360 v2.0 (stack Laravel/Angular). Périmètre Android exclu de ce document.*
