# Kaikun 360

> **En une phrase :** une plateforme unique — site web et application — pour
> **acheter, louer, construire, voyager, se déplacer et confier son bien** au
> Sénégal, en toute confiance.

---

## Kaikun 360, c'est quoi ?

Aujourd'hui, une personne qui veut acheter un terrain, louer une maison pour le
week-end, faire construire, réserver une excursion ou louer une voiture avec
chauffeur doit passer par des dizaines d'interlocuteurs différents, sans garantie
de sérieux. **Kaikun 360 rassemble tout ça au même endroit**, avec un principe
central : la **confiance** (biens et prestataires vérifiés, suivi des demandes de
bout en bout, paiement encadré).

### À qui ça s'adresse

La plateforme sépare clairement **cinq types d'utilisateurs**, chacun avec son
espace :

| Utilisateur | Ce qu'il vient faire |
| --- | --- |
| **Client** | Chercher et demander un service : acheter, louer, réserver une nuitée, une voiture, une excursion… |
| **Propriétaire** | Déposer ses biens, les mettre en location, confier leur gestion. |
| **Prestataire** | Proposer ses services (voiture, pirogue, circuit touristique, construction, guide…). |
| **Entreprise** | Organiser des demandes groupées : séminaires, team building, sorties. |
| **Équipe Kaikun** | Piloter la plateforme depuis les coulisses : valider, suivre, encadrer, gérer les paiements. |

### Ce que la plateforme permet (les 9 univers)

| Univers | En clair |
| --- | --- |
| **Immo** | Achat, vente et location mensuelle de biens immobiliers. |
| **Stay** | Location courte durée : nuitées, hébergements meublés. |
| **Manage** | Gestion locative pour propriétaires (loyers, incidents, reversements). |
| **Build** | Construction et rénovation : simulateur de budget, devis, suivi de chantier. |
| **Explore** | Tourisme : circuits, excursions, expériences. |
| **Mobility** | Transport : voiture, navette aéroport, bus, 4×4, pirogue, avec ou sans chauffeur. |
| **Diaspora** | Accompagnement à distance des Sénégalais de l'étranger (vérification, achat, suivi vidéo). |
| **Team Building** | Offres groupées pour entreprises et institutions. |
| **Pro** | Place de marché des prestataires certifiés (inscription, validation, notation). |

---

## Comment c'est construit

Le projet a **deux grandes parties** :

- **Le « moteur » (backend)** — invisible pour l'utilisateur, c'est le cerveau qui
  stocke les données, applique les règles métier et sécurise tout. Développé avec
  la technologie **Laravel**. Il expose une **API** : une sorte de standard de
  communication que n'importe quel écran (site web, application mobile future)
  peut utiliser.
- **Le « visible » (frontend)** — le **site web** avec lequel l'utilisateur
  interagit réellement. Développé avec la technologie **Angular**.

**Et le mobile ?** L'application mobile native (Play Store / App Store) viendra
plus tard : comme le moteur expose déjà une API standard, elle pourra être ajoutée
**sans rien refaire côté moteur**. En attendant, le site web sera « installable »
sur téléphone (technologie PWA).

---

## Où en est le projet ?

- ✅ **Le moteur (backend) est terminé.** Les 9 univers, les 5 espaces, la
  sécurité, les paiements, les notifications : tout est développé et couvert par
  des tests automatiques. Voir [`backend/README.md`](backend/README.md).
- 🚧 **Le site web (frontend) est en cours.** Le socle graphique et
  l'**authentification** (créer un compte, se connecter, vérifier son compte,
  mot de passe oublié) sont faits. Voir [`frontend/README.md`](frontend/README.md).
- ⏳ **Hors code** (à faire par le client / en parallèle) : nom de domaine,
  hébergement, compte marchand pour les paiements, validation juridique. Détaillé
  en fin de document.

> **La suite de ce document** est le **journal de bord détaillé** du
> développement : chaque phase (backend `B0→B17`, frontend `F0→F9`) liste toutes
> les tâches, cochées au fur et à mesure. C'est un outil de suivi technique — la
> présentation ci-dessus suffit pour comprendre le projet dans les grandes lignes.

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
- [x] Logique de favoris (initialement user/property ; **généralisée depuis en favoris polymorphes tous univers** — voir « Écran Favoris » en F3).
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
- [x] Logique de check-in / check-out et statut de ménage (rattachée au back-office, section B13). — _livré en B13.6._
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
- [x] Endpoint `GET /requests/{id}` — détail d'une de mes demandes (réservé au propriétaire, 403 sinon).
- [x] Endpoint `PATCH /requests/{id}/status` — changement de statut (réservé agents/admin).
- [x] Endpoint `GET /bookings/my` — réservations de l'utilisateur connecté.
- [x] Endpoint `GET /bookings/{id}` — détail d'une de mes réservations (réservé au titulaire, 403 sinon).
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
- [x] Endpoint admin de modération des avis (lié au back-office, section B13).
- [x] Policy : un avis ne peut être laissé que par un utilisateur ayant effectivement consommé le service/bien concerné.

### Phase B13 — Back-office / Admin (API)

- [x] Endpoint `GET /admin/dashboard` — demandes du jour, revenus estimés, biens en attente, prestataires à valider, alertes, KPI.
- [x] Endpoint `GET /admin/queue` — file de validation (biens, véhicules, circuits, prestataires).
- [x] Endpoint `PATCH /admin/validate/{type}/{id}` — validation ou refus générique par type de ressource.
- [x] Endpoint `GET /admin/users` et `PATCH /admin/users/{id}` — gestion des comptes, rôles, statut, désactivation.
- [x] Endpoint `GET /admin/reports/export` — export comptable et reporting.
- [x] Endpoints de paramétrage : villes, catégories, tarifs, commissions, FAQ, contenu des pages.
- [x] Endpoints de gestion documentaire transverse (mandats, contrats, preuves, pièces prestataires).
- [x] Endpoints spécifiques nuitées (calendrier global, caution, statut ménage/check-in/check-out) pour vue back-office.
- [x] Endpoints spécifiques gestion locative (contrats, loyers, incidents, dépenses, reversements) pour vue back-office.
- [x] Endpoints spécifiques construction (devis, prestataires BTP, jalons, rapports) pour vue back-office.
- [x] Endpoints spécifiques mobilité (véhicules, chauffeurs, disponibilités, assurances) pour vue back-office.
- [x] Endpoints spécifiques tourisme (circuits, destinations, guides, capacités groupe) pour vue back-office.
- [x] Endpoints spécifiques team building (demandes, packages, devis, affectation prestataires) pour vue back-office. — _file back-office exposée dans le module Team Building (`GET /team-building-requests`)._
- [x] Endpoints spécifiques diaspora (dossiers à forte valeur, vérification, reporting, priorités) pour vue back-office. — _vue priorisée exposée dans le module Diaspora (`GET /diaspora-projects`)._
- [x] Policies différenciées Agent / Admin / Super Admin selon le tableau de rôles du cahier des charges.

### Phase B14 — Paiement avec PayTech

- [ ] Créer un compte marchand PayTech (env. test/sandbox d'abord, puis demande d'activation production par email à PayTech).
- [x] Définir l'interface `PaymentProviderInterface` (méthodes : initiate, confirm, refund, status) — même si PayTech est le seul provider actif, l'interface reste en place pour ne jamais coupler le reste du code à un fournisseur précis.
- [x] Implémenter `PaytechProvider` (initiate via `POST /api/v1/payments` sur `engine-sandbox.pay.tech` puis `engine.pay.tech` en prod, en-tête `Bearer` avec la clé API boutique).
- [x] Stocker en configuration (jamais en dur dans le code) : clé API PayTech, clé de signature webhook (Signing Key), URL de webhook.
- [x] Migration `payments` (booking_id, provider, montant, statut, reference, mode) — statut aligné sur les états PayTech (`AUTHORIZED`, `COMPLETED`, `DECLINED`, `CANCELLED`).
- [x] Endpoint `POST /payments/initiate` — crée l'intention de paiement côté PayTech et retourne l'URL/redirection au frontend.
- [x] Endpoint `POST /payments/webhook` — réception des notifications PayTech.
- [x] **Validation obligatoire de la signature du webhook** : vérifier l'en-tête `Signature` (HMAC-SHA256 du corps JSON avec la Signing Key) avant de traiter toute notification — ne jamais faire confiance à un webhook non vérifié.
- [x] Mapper les statuts PayTech vers les statuts internes `payments`/`bookings` (ex. `COMPLETED` → booking confirmé, `DECLINED`/`CANCELLED` → booking non confirmé).
- [x] Implémenter le remboursement (`refund`) via PayTech pour les cas de caution à restituer ou d'annulation éligible (Mobility, Explore, Stay).
- [x] Logique de calcul et d'enregistrement des commissions Kaikun par transaction réussie.
- [x] Gérer le cas où le montant débité diffère du montant demandé (certains moyens PayTech) : réconciliation explicite, jamais de confirmation automatique sur une simple différence de montant non vérifiée.
- [x] Endpoint admin de supervision des paiements (liste, statut, recherche par référence) — lié à B13.
- [x] **Mode paiement manuel (Phase 1 du cahier des charges)** : `POST /payments/initiate` accepte `mode=manuel` (aucun appel PSP, renvoie les instructions de règlement Wave/Orange Money au numéro officiel) + `POST /admin/payments/{payment}/confirm` pour validation manuelle par l'admin. Logique de confirmation factorisée dans `PaymentConfirmationService` (source unique partagée avec le webhook PayTech).
- [ ] Tests en environnement sandbox PayTech avant toute bascule en production.
- [x] Tests : aucun module métier (Bookings, Quotes, Mobility, Explore) ne dépend directement de PayTech, uniquement de `PaymentProviderInterface`.

### Phase B15 — Sécurité, conformité et journalisation

- [x] Vérification email/téléphone obligatoire avant activation complète d’un compte.
- [x] Policies sur chaque ressource sensible + scopes Eloquent systématiques par propriétaire.
- [x] Audit log sur : validation de bien, modification de prix, validation de paiement, suppression de ressource.
- [x] Stockage des documents sur disque non public + URLs signées temporaires.
- [x] Statut `en_attente_validation` par défaut sur biens, véhicules, circuits, prestataires.
- [x] Rate limiting sur les endpoints sensibles (auth, paiement).
- [x] Politique de confidentialité techniquement reflétée (durée de conservation par type de donnée, anonymisation/suppression sur demande).
- [x] Revue de sécurité : aucun endpoint ne renvoie de données hors du périmètre autorisé du rôle appelant.

### Phase B16 — Notifications et communication

- [x] Configurer les canaux de notification Laravel (email, SMS optionnel, push différé pour plus tard avec mobile).
- [x] Intégration du module WhatsApp click-to-chat contextuel (génération de message prérempli selon page/service).
- [x] Jobs asynchrones pour l'envoi de notifications (ne jamais bloquer la requête HTTP).
- [x] Templates de notification par type d'événement (changement de statut, nouveau devis, confirmation de réservation, document à fournir).

### Phase B17 — Durcissement et performance

- [x] Index de base de données sur les colonnes de filtrage fréquent (ville, statut, type, prix) sur toutes les tables de catalogue.
- [x] Mise en cache Redis des résultats de recherche/catalogue les plus consultés.
- [x] Tests de charge sur les endpoints de catalogue et de recherche.
- [x] Revue complète des Form Requests (aucune donnée non validée ne doit atteindre la couche métier).
- [x] Documentation technique des endpoints (a minima OpenAPI ou commentaires structurés).

### Phase B18 — Intégrations & automatisation (n8n, SMS Orange)

- [x] Socle de webhooks sortants signés (HMAC) vers n8n + catalogue d'événements documenté (`WEBHOOKS.md`) + commande de test.
- [x] `OrangeSmsProvider` (canal SMS via l'API SMS d'Orange/Sonatel), testé via `Http::fake`. — _Reste action client : souscrire la SMS API sur developer.orange.com + essai sandbox réel._

_(Les Network APIs Orange — vérification de numéro / SIM Swap — ont été écartées : non retenues pour l'application.)_

### Phase B19 — Connexion Google (OAuth)

- [x] Endpoint `POST /auth/google` : vérification de l'ID token Google (audience contrôlée) + find-or-create ; nouveau compte = profil **client** (e-mail vérifié par Google → compte actif). Testé via `Http::fake`. — _Reste action client : créer les identifiants OAuth (Google Cloud Console) → `GOOGLE_CLIENT_ID`._
- [x] Hook frontend `AuthService.loginWithGoogle()` (le bouton Google s'ajoutera à l'écran de connexion en F1).

---

## FRONTEND — Angular

### Phase F0 — Initialisation et socle technique

- [x] Initialiser le projet Angular (dernière version stable [v22], configuration TypeScript stricte). — _standalone components._
- [x] Mettre en place le routing principal avec lazy loading par feature module. — _routeur en place (`provideRouter`) ; lazy loading via `loadComponent` appliqué dès F1._
- [x] Créer le `CoreModule` : `AuthService`, `TokenInterceptor`, `ErrorInterceptor`. — _approche standalone : dossier `core/` (pas de NgModule)._
- [x] Créer le `SharedModule` : composants UI réutilisables (cartes de bien/service, galerie photo, badges de vérification, boutons CTA). — _dossier `shared/` : `app-listing-card`, `app-gallery`, `app-verification-badge`, `app-orbit-hero`, `app-catalog`, `app-search-engine`, `app-lead-form`, `app-reviews`, `app-whatsapp-button`, `app-detail-layout` (F2.6), header/footer._
- [x] Mettre en place le design system Kaikun (couleurs #0348FB et #38A774, typographie, composants de base) conforme à la charte.
- [x] Définir les modèles TypeScript miroir des API Resources Laravel (Property, Stay, Vehicle, Experience, Request, Quote, Booking, Payment, User, Review, Media).
- [x] Mettre en place les guards (`AuthGuard`, `RoleGuard`).
- [x] Mettre en place la gestion centralisée des erreurs HTTP (401 → redirection login, 422 → affichage erreurs de formulaire, 500 → page d'erreur générique).
- [x] Configurer l'environnement (URLs API par environnement dev/prod).

### Phase F1 — Authentification et onboarding

- [x] Page d'inscription avec choix de profil (client, propriétaire, prestataire, diaspora, entreprise). — F1.2.
- [x] Page de connexion (email ou téléphone) + bouton **Connexion Google** (masqué tant que `googleClientId` non fourni). — F1.1 / F1.4.
- [x] Écran de vérification (code SMS/email). — F1.3.
- [x] Écran de récupération de compte. — F1.3 (mot de passe oublié → réinitialisation par code).
- [x] Gestion du stockage du token (en mémoire/state, jamais en localStorage non sécurisé) et du rafraîchissement de session. — Jeton en mémoire (signal `AuthService`) ; reconnexion silencieuse volontairement reportée.

### Phase F2 — Pages publiques (PublicModule)

- [x] Page Accueil : hero, recherche rapide, présentation des espaces, services prioritaires, éléments de confiance. <!-- F2.2 : hero éditorial + recherche intégrée, grille des 9 univers, protocole de confiance, vitrine catalogue (API réelle), diaspora, services complémentaires, simulateur (teaser), statistiques, appel final -->
- [x] Page Diaspora : page dédiée `/diaspora` (F2.5) — protocole de confiance + étapes + formulaire de contact (voir plus bas).
- [x] Composant simulateur : simulateur de budget calculant en direct sur `/construction` (F2.5) — miroir du calcul backend B5.4.
- [x] Page Immobilier : filtres biens (villes, type, prix), vérification, demande de visite. <!-- F2.3 : page univers /immobilier (bandeau + catalogue filtrable) + fiche /immobilier/:id (description, localisation, carte, vérification, formulaire de demande de visite POST /requests). Filtre géo par ID = affiné avec le sélecteur de villes ultérieur. -->
- [x] Page Nuitées : calendrier, photos, équipements, prix/nuit, disponibilité. <!-- F2.3 : page univers /nuitees + fiche /nuitees/:id (équipements, règlement, modalités, calendrier de disponibilité GET /stays/:id/availability, avis GET /reviews, demande de réservation). Photos réelles = quand les médias seront exposés dans les Resources (repli dégradé pour l'instant). -->
- [x] Page Gestion locative : page de conversion `/gestion-locative` (F2.5) — promesse (mandats, loyers/quittances, reporting, incidents, reversements), étapes, bénéfices, formulaire de mise en relation (POST /requests service_type=manage). La gestion opérationnelle vit dans l'espace connecté/back-office.
- [x] Page Construction : page de conversion `/construction` (F2.5) — étapes, atouts (artisans vérifiés, suivi filmé/daté, paiements par jalons), **simulateur de budget interactif** (objectif/surface/finition → estimation FCFA en direct) + formulaire de devis pré-rempli par le simulateur (POST /requests service_type=build).
- [x] Page Tourisme & expériences : destinations, programmes, durée, inclusions, guide, restauration. <!-- F2.4 : page univers /tourisme (bandeau + catalogue filtrable) + fiche /tourisme/:id (programme, inclusions structurées, durée, places restantes GET /experiences/:id/availability, avis GET /reviews, demande de réservation POST /requests service_type=explore). -->
- [x] Page Transport & mobilité : location voiture particulière, voiture touristique, navette AIBD, bus, minibus, 4x4, pirogue. <!-- F2.4 : univers Transport (véhicules) = /transport + fiche /transport/:id (caractéristiques, chauffeur, caution, avis, demande POST /requests service_type=mobility). Univers Mobilité (navettes/transferts) = vitrine /mobilite (pas d'endpoint de détail backend → vitrine + réservation via conseiller). Header et tuiles d'accueil recâblés. -->
- [x] Page Diaspora : page de conversion `/diaspora` (F2.5) — protocole de confiance (vérification documentée, tout filmé/daté, numéro de suivi unique), étapes (référent unique), bénéfices, formulaire de contact (POST /requests service_type=diaspora). Cœur du positionnement anti-arnaque.
- [x] Page Team building : page de conversion `/team-building` (F2.5) — formules (journée cohésion, séminaire résidentiel, incentive), étapes, formulaire de demande de devis (POST /requests service_type=team_building).
- [x] Page Kaikun Pro : page de recrutement prestataires `/pro` (F2.5) — atouts, audiences (agences/artisans/transport-tourisme-services), étapes de certification ; CTA = inscription prestataire (`/auth/inscription`), pas de formulaire de demande.
- [x] Page À propos : page de contenu éditorial `/pages/a-propos` (F2.8) — servie par le backend (GET /pages/{slug}), éditable en back-office ; corps HTML assaini via `[innerHTML]`.
- [x] Page FAQ : `/faqs` (F2.8) — entrées publiées (GET /faqs) regroupées par catégorie, accordéons natifs `<details>` ; états chargement/vide/échec gracieux.
- [x] Page Contact : `/contact` (F2.8) — **formulaire public** (POST /contact, F2.8.1) traité par l'équipe (`can:traiter:demandes`) + **carte Google Maps du siège** (embed, coordonnées via GET /contact-info, éditables au back-office) + WhatsApp contextuel + e-mail + orientation vers les parcours métier. Adresse/coordonnées/e-mail non codés en dur.
- [x] Pages légales : route générique `/pages/:slug` (F2.8) — mentions légales, CGU, politique de confidentialité (et À propos) servies par slug. Contenu de démonstration seedé côté backend (`ContentSeeder`).
- [x] Composant moteur de recherche global (service + ville + budget + dates + profil utilisateur). <!-- F2.1 : univers + ville/mots-clés + budget ; dates & profil affinés en F2.3 -->
- [x] Composant catalogue filtrable et triable, réutilisé sur toutes les pages d'univers. <!-- F2.1 -->

- [x] Composant fiche détaillée (photos, description, localisation, prix, disponibilité, règles, preuves, avis, CTA). <!-- F2.6 : coquille générique `app-detail-layout` (bandeau titre + fil d'Ariane + informations clés + galerie + corps 2 colonnes par projection de contenu). Les 4 fiches d'univers (Immobilier, Nuitées, Tourisme, Transport) sont refactorées dessus. Galerie affichée quand des photos sont fournies (masquée tant que les médias ne sont pas exposés par l'API — dégradation gracieuse). -->
- [x] Composant carte cliquable : `app-listing-card` mène à la fiche détaillée (lien étiré) — F2.3.
- [x] Composants de formulaires intelligents : demande client, dépôt de bien, inscription prestataire, demande diaspora, devis team building. <!-- F2.5 : composant réutilisable `app-lead-form` (demande de contact auth-gated, POST /requests) partagé par les pages de conversion. F2.7 : formulaires restants — dépôt de bien `/deposer-un-bien` (POST /properties, sélecteurs géo région→département→commune en cascade sur GET /regions|departments|communes ; auth + compte vérifié), inscription prestataire `/pro/inscription` (POST /providers + statut via GET /providers/mine), réponse au devis `/devis/:id` (GET + PATCH /quotes/{id}, accepter/refuser). Team building = déjà couvert par le lead-form (F2.5). Photos/documents du dépôt = étape ultérieure (espace connecté). -->
- [x] Composant simulateur immobilier/construction (budget, ville, surface, objectif, niveau de finition). <!-- F2.5 : simulateur de construction interactif sur /construction (objectif/surface/finition → estimation FCFA en direct), miroir du calcul backend `ConstructionEstimator` (B5.4) dans `features/build/construction-estimator.ts`. Volet immobilier (estimation de prix) non prévu par le backend à ce stade. -->

- [x] Composant module WhatsApp contextuel (message prérempli selon page/service). <!-- F2.6 : `app-whatsapp-button` + `WhatsAppService` (GET /whatsapp/link, B16.3) ; le message est prérempli selon le contexte (`subject`/`reference`), le numéro provient du back-office (jamais codé en dur). Posé sur les 4 fiches, la vitrine Mobilité et la page Diaspora. Le bouton se masque tout seul si aucun numéro de support n'est paramétré. -->
- [x] Composant galerie image (image principale, alt text). <!-- F2.6 : `app-gallery` enrichie (image principale cliquable → vue plein écran/lightbox, navigation clavier ←/→ et Échap, flèches, compteur i/n, repère « photo principale », repli « Aucune photo disponible »). Reçoit une liste d'URLs ; sera alimentée par de vraies photos quand les médias seront exposés par l'API (compression/statut de validation = côté upload back-office). -->
- [~] Composant témoignages et preuves (labels de vérification, documents visibles selon niveau d'autorisation). <!-- F2.6 : `app-reviews` (note moyenne + étoiles + liste des avis publiés) mutualisé sur les fiches Nuitées/Tourisme/Transport. Le badge de vérification (`app-verification-badge` / `.uni-detail-verified`) est déjà en place. Documents visibles selon niveau d'autorisation = espace connecté (F3+). -->
- [x] Mise en place d'Angular Universal (SSR) sur l'ensemble des pages publiques. <!-- F2.9 : `@angular/ssr` + `outputMode: server` + serveur Node/Express (`src/server.ts`). Toutes les routes publiques en `RenderMode.Server` (rendu à la demande, adapté aux pages dynamiques `:id`/`:slug`/`?query` alimentées par le backend). Hydratation + transfer-cache HTTP (`provideClientHydration(withEventReplay())`) pour ne pas refetch côté client, `withFetch()` sur le HttpClient. Vérifié : les 12 routes publiques renvoient un HTML rendu serveur (`ng-server-context="ssr"`, HTTP 200) avec données réelles du backend, titres d'onglet corrects et 404 gracieux. Budgets de style CSS relevés (8/16 kB) pour que le build production passe. ⚠️ Ajouter les domaines de prod dans `angular.json → security.allowedHosts` avant mise en ligne. Détail : `frontend/README.md` (section SSR). -->
  <!-- 🎉 Phase F2 (Pages publiques) CLÔTURÉE : F2.1 socle data/recherche/catalogue → F2.9 SSR. -->

**🎉 Phase F2 (Pages publiques) terminée.** Puis **🎉 Phase F3 (Espace client) terminée** : les six écrans (profil, demandes, réservations, favoris, notifications, messagerie) + le câblage des formulaires de demande, vérifiés de bout en bout. Prochaine grande étape : les autres espaces personnels (F4 propriétaire, F5 prestataire, F6 entreprise) puis back-office (F7).

### Phase F3 — Espace client (ClientSpaceModule)

- [x] **Socle de l'espace (F3.1)** : layout authentifié `/mon-espace` à navigation latérale (guard d'accès + redirection connexion), page d'accueil (tableau de bord) avec carte des sections, en-tête conscient de la session (« Mon espace » / « Déconnexion »). <!-- Les 6 écrans ci-dessous se branchent sur ce socle en F3.2 → F3.7. -->
- [x] **Écran Favoris (F3.5, puis généralisé à TOUS les univers)** : liste paginée des favoris du client, présentés avec la **même carte que le catalogue** (`app-listing-card`) — cliquer mène à la fiche ; **retrait** en un geste via une confirmation inline. **Généralisation majeure** : les favoris, d'abord limités à l'immobilier, sont devenus **polymorphes** (bien, nuitée, véhicule, expérience, mobilité) — nouveau socle backend transversal (table `favorites` avec `favoritable_type`/`favoritable_id`, `App\Support\Favoritables`, endpoints `/api/v1/favorites` : liste multi-univers, `ids` groupés, `POST {type,id}`, `DELETE /{type}/{id}`) et **cœur d'ajout/retrait directement sur les cartes du catalogue et de l'accueil** (`FavoriteStore` partagé ; cœur visible même en anonyme, qui invite alors à se connecter). Le `DemoSeeder` amorce des favoris multi-univers pour le client de démonstration.
- [x] **Écran Mes demandes (F3.3)** : liste des demandes de service du client connecté (`GET /requests/my`, paginée), chaque demande présentée en carte (référence, univers, budget indicatif, localité, message) avec une **chronologie visuelle du statut** matérialisant la machine à états backend (reçu → vérification → visite → devis → négociation → clôturé) — étapes franchies / étape courante / à venir. **Chaque carte est cliquable** vers un **écran de détail** (`/mon-espace/demandes/:id`, `GET /requests/{id}` réservé au propriétaire). Un bouton **« ← Retour »** (composant partagé `app-back-link`, retour **historique**) est présent sur la liste comme sur le détail : il ramène à la **page précédente** (les notifications quand on arrive par une notification, la liste depuis le détail…).
- [x] **Écran Réservations (F3.4)** : liste des réservations du client tous univers confondus (`GET /bookings/my`, paginée) — nuitées, véhicules, expériences, trajets — avec l'élément réservé, les dates, le montant, la caution et le statut ; **annulation** en un geste là où le backend l'autorise (véhicules et expériences non encore annulés), avec affichage de l'éligibilité au remboursement. La ressource `BookingResource` a été enrichie (`type`, `type_label`, `item_label`, `cancellable`) pour cet écran. **Chaque carte est cliquable** vers un **écran de détail** (`/mon-espace/reservations/:id`, `GET /bookings/{id}` réservé au titulaire, en lecture seule). Un bouton **« ← Retour »** (composant partagé `app-back-link`, retour **historique**) est présent sur la liste comme sur le détail : il ramène à la **page précédente** (les notifications quand on arrive par une notification, la liste depuis le détail…).
- [x] **Écran Messages (F3.7)** : messagerie du client, sur un **socle backend créé de zéro et générique** (tables `conversations` + `conversation_user` [participants, `last_read_at` par participant] + `messages`), **réutilisable par les espaces pro F4/F5/F6**. Un écran liste les **conversations** (`GET /messages`, paginé, `unread_count` joint), chacune avec correspondant, aperçu du dernier message et **pastille de non-lus** ; l'écran de **fil** (`GET /messages/{id}`, qui **marque comme lu**) affiche les messages en **bulles** (les siens à droite, le correspondant à gauche) et permet de **répondre** (`POST /messages/{id}/messages`). Ouverture d'un nouveau fil (`POST /messages`, dédoublonnage des fils directs) et compteur global (`GET /messages/unread-count`). Chaque message **notifie** les autres participants (canal `database`, via une `NewMessageNotification` → cloche F3.6). **Isolation stricte** (fil d'autrui → 404). **Dernier des six écrans de l'espace client** (il ne reste ci-dessous que le branchement transverse des formulaires de demande).
- [x] **Écran Notifications (F3.6)** : centre de notifications du client (`GET /users/me/notifications`, paginé, `unread_count` joint aux métadonnées), alimenté par le **canal `database` de Laravel** (créé pour l'occasion : table `notifications`, ajouté aux notifications métier demande/devis/réservation). Chaque notification est une **carte cliquable** (icône teintée par catégorie, titre, message, date) → **marquée comme lue** (`PATCH …/{id}/read`) puis navigation vers l'écran concerné ; bouton **« Tout marquer comme lu »** (`PATCH …/read-all`). La **cloche de l'en-tête** porte une **pastille** du nombre de non-lues. Isolation stricte par utilisateur (notification d'autrui → 404).
- [x] **Écran Profil (F3.2 / F3.2b)** : identité & coordonnées **toutes modifiables** via `PATCH /users/me` — nom, **e-mail et téléphone** (changement = **re-vérification** avec saisie du code envoyé au nouveau contact), **adresse** + **localisation en cascade** Région → Département → Commune (référentiel géo) ; **changement de mot de passe** (`PATCH /users/me/password`, exige le mot de passe actuel) ; pièces justificatives (liste + dépôt PDF/JPG/PNG ≤ 5 Mo via `GET`/`POST /users/me/documents`, téléchargement par URL signée) ; suppression du compte (anonymisation RGPD `DELETE /users/me`, derrière confirmation). Lien « Mon profil » activé dans le menu utilisateur de l'en-tête.
- [x] **Connexion de tous les formulaires de demande aux endpoints `requests`/`bookings`** : le `app-lead-form` générique (Construction, Diaspora, Gestion locative, Team building) dépose une vraie demande via `POST /requests` (avec le bon `service_type`), et les fiches (nuitées, véhicules, expériences) créent des réservations. **Vérifié de bout en bout** : une demande soumise depuis la vitrine apparaît immédiatement dans « Mes demandes » de l'espace client (référence + chronologie de statut).

### Phase F4 — Espace propriétaire (OwnerSpaceModule)

> 🏗️ **En cours.** L'espace propriétaire est monté sous `/espace-proprietaire`
> (réservé au rôle `proprietaire`). Il réutilise le **shell app-shell généralisé**
> (menu latéral sombre + en-tête épuré), désormais extrait en un **layout partagé
> paramétré par espace** (`SPACE_CONFIG`) qui sert aussi l'espace client et les
> futurs espaces pro. **F4.1 livré** : le **tableau de bord de gestion locative**
> (`GET /manage/dashboard` — mandats actifs, loyers encaissés/impayés, dépenses,
> reversements, incidents ouverts), avec données de démonstration seedées pour le
> propriétaire de démo. **F4.2 livré** : l'écran **« Mes biens »** (liste de tous
> ses biens quel que soit le statut + fiche), qui matérialise le **suivi de
> validation** de chaque annonce.

- [x] **Écran « Mes biens » (F4.2)** : liste de tous les biens du propriétaire, **tous statuts confondus** (`GET /properties/mine`) — au contraire du catalogue public qui ne montre que les biens publiés. Chaque carte cliquable porte une **pastille de statut de validation** (publié, en attente, rejeté, suspendu/archivé) ; la **fiche** (`GET /properties/mine/{id}`, réservée au propriétaire → 404 sinon) détaille le statut avec une explication, la description, les caractéristiques, la localisation et les dates. Lecture seule (le dépôt/édition arrive en F4.3).
- [ ] Formulaire de dépôt de bien (photos, localisation, type de location, documents).
- [ ] Choix du mode de location (mensuelle, nuitées, formule mixte).
- [ ] Tableau de bord propriétaire : demandes, visites, réservations, loyers, incidents. *(F4.1 : volet gestion locative — loyers, reversements, incidents — livré.)*
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
