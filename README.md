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
- [x] Système de notation/avis spécifique aux prestataires (`rating_avg`/`rating_count` remplis par `RatingAggregator` en B12 ; **F5.5** ajoute les avis directs après mission et l'écran « Avis reçus »).
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
- [x] Endpoints `GET/POST /admin/team` + `PATCH /admin/team/{id}` — gestion de l'**équipe back-office** (annuaire, enrôlement d'un agent/admin avec code d'invitation e-mail, pilotage rôle/statut), garde-fous de hiérarchie (escalade, auto-modification, protection super_admin). — _F7.1.a._
- [x] Endpoints `GET/PUT /admin/team/{id}/permissions` — **délégation des dossiers par personne** (« grant pur ») : le rôle `agent_kaikun` n'ouvre plus que l'accès, chacune des **12 permissions** de traitement est déléguée individuellement (`AdminPermission`), la gouvernance exigeant un super_admin. — _F7.1.b._
- [x] Endpoints pointeuse `POST /admin/attendance/clock-in|clock-out`, `GET /admin/attendance/me`, `GET /admin/attendance` — **présences de l'équipe** (entrée/sortie, feuille mensuelle détail/récapitulatif, export CSV). — _F7.1.c._

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
- [x] **Projets diaspora (F3.8)** — mise en conformité du critère CDC §15 (« un dossier diaspora peut être **créé, suivi et enrichi de rapports** »), resté sans interface alors que le backend l'exposait déjà. Nouvelle rubrique **« Projets diaspora »** de l'espace client (`/mon-espace/diaspora`) : **liste** des dossiers (`GET /diaspora-projects/mine`, statut + nombre de rapports), **lancement** d'un projet (`POST /diaspora-projects` : type achat/construction/gestion locative, pays de résidence, budget, priorité) et **détail avec chronologie des rapports** (`GET /diaspora-projects/{id}` + `/reports`) — chaque rapport affichant type, date, commentaire, **galerie photos** et **lien vidéo**. Le client est en **lecture seule** sur les rapports (déposés par l'agent affecté, back-office) ; isolation par policy (projet d'autrui → 404). Service `core/api/diaspora.service.ts` + modèle `models/diaspora.model.ts` ; pont depuis la page publique `/diaspora`. Écrans dans `features/diaspora/diaspora-projects/`, montés dans `account.routes.ts`.

### Phase F4 — Espace propriétaire (OwnerSpaceModule)

> ✅ **Terminée.** L'espace propriétaire est monté sous `/espace-proprietaire`
> (réservé au rôle `proprietaire`). Il réutilise le **shell app-shell généralisé**
> (menu latéral sombre + en-tête épuré), désormais extrait en un **layout partagé
> paramétré par espace** (`SPACE_CONFIG`) qui sert aussi l'espace client et les
> futurs espaces pro. **F4.1 livré** : le **tableau de bord de gestion locative**
> (`GET /manage/dashboard` — mandats actifs, loyers encaissés/impayés, dépenses,
> reversements, incidents ouverts), avec données de démonstration seedées pour le
> propriétaire de démo. **F4.2 livré** : l'écran **« Mes biens »** (liste de tous
> ses biens quel que soit le statut + fiche), qui matérialise le **suivi de
> validation** de chaque annonce. **F4.3 livré** : le **dépôt et l'édition d'un
> bien** depuis l'espace, avec le **mode de location** (mensuelle / nuitées /
> mixte) — ce dernier a nécessité de créer côté backend la **gestion de la config
> nuitées par le propriétaire** (`PUT`/`DELETE /properties/{id}/stay`). **F4.4
> livré** : l'écran **« Gestion locative »** (liste des mandats + fiche avec
> résumé financier, loyers/reversements/incidents et **rapport mensuel** par mois)
> — en lecture seule sur le module Manage, dont la fiche mandat expose désormais
> ses lignes détaillées. **F4.5 livré** (clôture F4) : l'écran **« Documents »**
> — gestion des pièces justificatives **par bien** (liste des biens avec compteur,
> puis dépôt / téléchargement par URL signée / suppression des pièces d'un bien).

- [x] **Écran « Mes biens » (F4.2)** : liste de tous les biens du propriétaire, **tous statuts confondus** (`GET /properties/mine`) — au contraire du catalogue public qui ne montre que les biens publiés. Chaque carte cliquable porte une **pastille de statut de validation** (publié, en attente, rejeté, suspendu/archivé) ; la **fiche** (`GET /properties/mine/{id}`, réservée au propriétaire → 404 sinon) détaille le statut avec une explication, la description, les caractéristiques, la localisation et les dates.
- [x] **Formulaire de dépôt / édition de bien (F4.3)** : un **seul écran** sert la création (`biens/nouveau` → `POST /properties`) et l'édition (`biens/:id/modifier` → `PATCH /properties/{id}`, préremplie depuis la fiche privée). Localisation en **cascade** région → département → commune, **compte vérifié requis**, redirection vers la fiche après enregistrement. *(Les photos/documents du bien restent hors périmètre — voir F4.5.)*
- [x] **Photos des biens (F4.3)** : chaque bien peut être illustré — c'est ce qui donne confiance au client et lui permet de choisir. L'infrastructure média (table `media` polymorphe, upload compressé) existait depuis B12.1 mais n'était **branchée sur aucun bien** : `Property::media()` et les clés `photos` / `photo_url` de `PropertyResource` l'exposent désormais (couverture d'abord, médias masqués exclus, sans N+1). Le propriétaire dépose ses photos depuis le formulaire, choisit sa **couverture** (`PATCH /media/{id}/primary`) et en retire ; les photos s'affichent sur sa fiche, sur les **cartes du catalogue** et dans la **galerie de la fiche publique** (bien et nuitées). Un bien sans photo garde la vignette dégradée de repli.
- [x] **Choix du mode de location (mensuelle, nuitées, formule mixte) (F4.3)** : un sélecteur pilote les champs affichés (loyer mensuel et/ou bloc nuitées : prix/nuit, caution, capacité, nuits min/max, horaires d'arrivée/départ) **et** les appels d'enregistrement. Côté backend, la config nuitées d'un bien est désormais gérée par son propriétaire via **`PUT /properties/{id}/stay`** (upsert idempotent, réactive une config désactivée) et **`DELETE /properties/{id}/stay`** (supprime, ou **désactive** si des réservations existent, pour préserver l'historique) — autorisés par la `PropertyPolicy`.
- [ ] Tableau de bord propriétaire : demandes, visites, réservations, loyers, incidents. *(F4.1 : volet gestion locative — loyers, reversements, incidents — livré.)*
- [x] **Écran « Gestion locative » (F4.4)** : liste des **mandats** du propriétaire (`GET /manage/mandates/mine`) puis **fiche d'un mandat** (`GET /manage/mandates/{id}`, réservée au propriétaire → 404 sinon) — résumé financier (5 KPI), conditions du mandat, **lignes récentes** (loyers, reversements, incidents) et **rapport mensuel** (`GET .../report?month=YYYY-MM`) recalculable via un sélecteur de mois : loyers encaissés/impayés, dépenses, **commission Kaikun**, **net à reverser** et reversements du mois. En **lecture seule** (les mandats sont établis par les agents) ; la fiche mandat expose désormais ses lignes détaillées (`MandateResource`).
- [x] **Écran « Documents » (F4.5)** : gestion des pièces justificatives **par bien**. Un premier écran liste les biens du propriétaire (`GET /properties/mine`) avec, pour chacun, son **nombre de documents** (`documents_count`, via `withCount`) ; cliquer un bien ouvre la gestion de ses pièces (`documents/:id`) : liste (`GET /properties/{id}/documents`), **dépôt** (`POST`, type titre foncier/bail/plan/autre + fichier PDF/JPG/PNG ≤ 5 Mo, contrôlé en amont), **téléchargement** (URL **signée temporaire**, le chemin de stockage n'est jamais exposé) et **suppression** (`DELETE`, retire la ligne **et** le fichier sur disque privé). Réservé au propriétaire du bien via la policy `manageDocuments` (403 sinon). Le statut de validation d'une pièce est posé par un agent Kaikun (lecture seule côté propriétaire).

### Phase F5 — Espace prestataire (ProviderSpaceModule)

> 🎉 **Terminée.** L'espace prestataire est monté sous `/espace-prestataire`
> (réservé au rôle `prestataire`) et réutilise le **shell app-shell généralisé**
> (`SPACE_CONFIG`), comme les espaces client (F3) et propriétaire (F4). **F5.1
> livré** : le **socle** (navigation des 6 rubriques, garde de rôle, liens
> transverses profil/notifications cloisonnés) et le **tableau de bord**
> (`GET /providers/mine`) qui affiche l'**état du dossier prestataire** — statut
> de validation, note moyenne, avis reçus, certifications (vérifiées ou en cours)
> et avertissements. **F5.2 livré** : l'écran **« Missions reçues »**
> (`GET /provider-missions/mine`) — liste paginée des missions confiées, avec
> montant, commission Kaikun, **net** revenant au prestataire, date prévue et
> statut ; des **actions** font progresser chaque mission (accepter / refuser une
> mission affectée, la démarrer, la marquer terminée) via
> `PATCH /provider-missions/{id}/{action}`. Le compte prestataire de démonstration
> reçoit un **profil marketplace seedé** (validé, noté, certifié) et **cinq
> missions** à statuts variés. **F5.3 livré** : l'écran **« Revenus &
> commissions »** (nouvel endpoint d'agrégat `GET /provider-missions/earnings`) —
> synthèse du **réalisé** (missions terminées : chiffre d'affaires, commission
> Kaikun, net encaissé) et de l'**à venir** (missions acceptées ou en cours :
> engagé pas encore encaissé). **F5.4 livré** : l'écran **« Disponibilités »** —
> backend neuf (tables `provider_weekly_availabilities` + `provider_unavailabilities`,
> 4 endpoints sous `/providers/availability`) — combinant un **planning
> hebdomadaire récurrent** (7 jours, ouvert/fermé + horaires) et des **périodes
> d'indisponibilité** ponctuelles (congés) qui priment sur le planning.
> **« Mes services » livré** : l'écran d'**édition du dossier prestataire** —
> descriptif du service (raison sociale, catégorie, présentation) via le nouvel
> endpoint `PUT /providers/mine`, et **gestion des documents de certification**
> (ajout `POST /providers/certifications`, suppression `DELETE .../{id}`). Une
> modification du descriptif **ne relance pas** la validation, et un document
> ajouté reste « En vérification » jusqu'à revue back-office. **F5.5 livré**
> (clôture F5) : l'écran **« Avis reçus »** (`GET /providers/reviews`) réunit
> **deux sources d'avis** dans une notation unifiée — les avis publiés sur les
> **ressources** du prestataire (véhicules, expériences) et un **nouveau canal
> d'avis directs** le notant après une **mission terminée** (branché dans
> `Review::TYPES`, éligibilité par mission dans `ReviewPolicy`, agrégation étendue
> dans `RatingAggregator`). L'écran affiche une **synthèse de notation** (note
> moyenne, total, histogramme de répartition par étoiles) et la **liste des avis**
> (auteur, source, commentaire, date). **F5.6 livré** (mise en conformité CDC
> §5.2 / §15) : l'écran **« Mes offres »** branche enfin le **dépôt d'offres
> réservables** sur des endpoints backend déjà exposés mais sans interface —
> **véhicules** (`POST /vehicles`, `PATCH /vehicles/{id}`, `GET /vehicles/mine`)
> et **circuits touristiques** (`POST /experiences`, `GET /experiences/mine`). Le
> formulaire véhicule propose les **8 catégories distinctes** de `VehicleType` et
> adapte ses **champs de conformité** à la famille du type (assurance + identité
> chauffeur pour un motorisé ; gilets + conformité météo/prestataire pour une
> pirogue, cf. §12). Chaque offre affiche son **statut de validation** ; l'édition
> d'un véhicule le recharge via `OfferService.findMyVehicle` (le détail public ne
> renvoie que les véhicules publiés). Nouveau service `core/api/offer.service.ts`.
> **🎉 Phase F5 (Espace prestataire) terminée.**

- [x] Formulaire de dépôt de service (voiture, pirogue, circuit, BTP, guide, hébergement) avec documents de certification. — _F5.6 : « Mes offres » — dépôt/édition de **véhicules** (`POST/PATCH /vehicles`) et de **circuits** (`POST /experiences`) avec statut de validation ; « Mes services » gère en complément le descriptif (`PUT /providers/mine`) et les certifications._
- [x] Écran de gestion des disponibilités. — _F5.4 : planning hebdomadaire récurrent + périodes d'indisponibilité._
- [x] Écran des missions reçues et de leur statut. — _F5.2 : liste + actions de transition (accepter / refuser / démarrer / terminer)._
- [x] Écran des avis reçus et de la notation. — _F5.5 : avis sur les ressources + avis directs après mission (`GET /providers/reviews`), note moyenne + répartition._
- [x] Écran de suivi des revenus et commissions. — _F5.3 : synthèse réalisé / à venir (`GET /provider-missions/earnings`)._

### Phase F6 — Espace entreprise (EnterpriseSpaceModule)

- [x] Formulaire de demande de team building (participants, ville, dates, budget, activités, besoin transport/hébergement). — _F6 : `/espace-entreprise/demandes/nouvelle` (`POST /team-building-requests`, cahier §9.4)._
- [x] Écran de suivi des devis composés par l'admin. — _F6 : détail `/espace-entreprise/demandes/:id` (`GET /team-building-requests/{id}`) — lignes, sous-total, marge, total + **acceptation** d'un devis envoyé (`PATCH /team-building-quotes/{id}/accept`)._
- [x] Écran d'historique des commandes/demandes groupe. — _F6 : « Mes demandes » `/espace-entreprise/demandes` (`GET /team-building-requests/mine`, paginé, pastille de statut)._
- [x] **Messages dans l'espace entreprise** (cahier §5 « Messages = Tous ») — écrans de messagerie génériques réutilisés, rendus autonomes par `SPACE_CONFIG` ; **notif in-app** du devis envoyé (canal `database` ajouté à `TeamBuildingQuoteSentNotification`).

### Phase F7 — Back-office (AdminModule)

> **Shell DÉDIÉ et indépendant** (décision produit) : le back-office n'utilise PAS le shell des espaces utilisateurs. Il a sa propre racine, son guard de rôle strict et son identité « salle de contrôle » — niveau de sécurité maximal car « tout passe par là ». On commence par **F7.1 « Poste de commandement de l'équipe »** (super admin + sous-admins) avant le reste.
>
> **🎉 F7.1 « Poste de commandement de l'équipe » terminé** (backend + frontend, vérifié dans le navigateur). Backend (voir Phase B13) : **F7.1.a** équipe/enrôlement · **F7.1.b** délégation « grant pur » par personne · **F7.1.c** pointeuse · **F7.1.d** 2FA e-mail + session courte. Frontend : shell dédié `layouts/backoffice-layout/` sous `/back-office` (guard rôle staff), connexion 2FA, et les écrans ci-dessous.
>
> **▶ F7.2 « Les autres écrans du back-office » en cours** (frontend pur, branché sur l'API Admin déjà livrée en B13). **F7.2.a Validation** : file d'approbation générique des biens / véhicules / expériences / prestataires, avec le déposant (identité + contact) et décision valider/refuser. **F7.2.b Catalogues** : navigateur de supervision de toute l'offre (tous statuts), en lecture seule. **F7.2.c Nuitées** : calendrier des séjours + check-in / check-out + suivi du ménage. **F7.2.d Paiements** : supervision + confirmation manuelle Wave/OM + remboursement. **F7.2.e Dossiers** : supervision des suivis longs — demandes de construction et mandats de gestion locative, en lecture seule. **F7.2.f Comptes & documents** : annuaire de tous les comptes + **fiche détaillée** (identité, profil, pilotage rôle/statut/désactivation, demande de pièce, pièces déposées et **historique** du journal d'audit) + vue documentaire transverse (KYC, biens, certifications, reversements) — couvre les modules CDC §6 *Utilisateurs* et *Documents*. **F7.2.g Avis & qualité** : file de modération des avis (publier/rejeter) + supervision des prestataires (note agrégée + avertir/suspendre) — couvre le module CDC §6 *Avis et qualité* (les incidents restent dans l'écran Dossiers). **F7.2.h Team building** : file des demandes groupées entreprises + **fiche** (composition du devis pack ligne par ligne, envoi à l'entreprise) et **affectation des prestataires** — chaque affectation crée une mission Pro rattachée (prestataire validé, commission figée, cycle de mission) — couvre le module CDC §6 *Team building* dont l'« affectation prestataires » (ajout backend `team_building_request_id`+`category` sur `provider_missions`). **F7.2.i Diaspora** : file **priorisée** des dossiers à distance (filtres statut/priorité/recherche) + **fiche** de pilotage (re-priorisation, affectation d'un agent dédié explicite ou auto, progression/clôture du statut, **rapports de suivi** vérification/chantier/reporting) — couvre le module CDC §6 *Diaspora* ; ajout backend d'un endpoint de pilotage `PATCH /diaspora-projects/{id}` (statut/priorité sans effet de bord) qui manquait. **F7.2.j Mobilité** : deux réalités que le cahier des charges range sous un même module mais qui ne se pilotent pas pareil — la **flotte** (moyens de transport loués à la journée : voitures, bus, 4x4, pirogues, mise à disposition de chauffeur) et les **départs programmés** (trajets datés réservés à la place). D'où deux onglets. Flotte : tous statuts avec une colonne **conformité** qui est la vraie valeur ajoutée du back-office — assurance manquante, chauffeur non déclaré, pirogue sans gilets se repèrent d'un coup d'œil, le détail des manquements étant en infobulle (la grille reprend celle du `VehicleComplianceChecker` de B7.3, deux jeux d'exigences distincts fluvial / motorisé). Trajets : tous statuts avec le **remplissage** de chaque départ (jauge places prises / restantes, mention « Complet ») — les « disponibilités » du cahier. Ajouts backend : `GET /admin/mobility-services` (remplissage agrégé en une requête via `withSum`, statuts d'annulation dérivés de `BookingStatus::estAnnulee()`) et deux Resources back-office, `AdminVehicleResource` / `AdminMobilityServiceResource` — les champs de contrôle (n° d'assurance, identité du chauffeur) restent **hors** du catalogue public. Comme Catalogues et Dossiers, l'écran est en lecture seule : la décision d'approbation reste concentrée dans l'écran Validation (F7.2.a). **F7.2.k Tourisme** : même logique de découpage — les six éléments du module CDC (« circuits, destinations, programmes, guides, restaurants, capacités groupes ») ne vivent pas au même endroit dans le modèle, d'où **trois onglets**. **Circuits** : capacité groupe en jauge et **programme** rendu par les *inclusions* du circuit (restauration, guide, transport) ; ⚠️ un circuit n'a pas de date de départ, sa capacité est un **total** et le remplissage cumule toutes ses réservations — à ne pas confondre avec le départ daté de Mobilité. **Destinations** : les destinations ne sont pas une entité en base mais une **colonne** des circuits, restituée par agrégation (`GROUP BY`) — couverture, publiés vs en attente, capacité cumulée, fourchette de prix, avec chaînage vers les circuits de la destination. **Guides & restaurants** : servis par la marketplace Pro (`?category=guide,restauration`, filtre multi-valeurs ajouté). **Écart CDC signalé, non comblé** : la plateforme ne connaît guides et restaurants que comme catégories de prestataires et drapeaux d'inclusion — **aucun guide nommé n'est rattaché à un circuit précis** ; le combler demanderait un modèle d'affectation guide ↔ circuit, hors périmètre de la tranche. L'écran l'affiche explicitement plutôt que de le masquer. **F7.2.l Paramètres & contenu** — **dernier des 14 modules du CDC §6**. Quatre onglets, parce que les sept fonctions de la ligne CDC ne se pilotent pas de la même façon. **Réglages** : commissions & marges, coordonnées publiques, et le **barème du simulateur de construction** (les « tarifs ») — un objet imbriqué, aplati en chemins pour être éditable champ par champ sans coder sa structure en dur ; l'enregistrement n'envoie que les clés modifiées, sinon chaque valeur par défaut deviendrait une surcharge en base et un futur ajustement du code n'aurait plus d'effet. **Notifications** : jusqu'ici les canaux étaient codés en dur dans chaque `via()` — impossible de couper le SMS (facturé à l'envoi) sans redéployer. Un point de décision unique (`NotificationSettings`) arbitre désormais : événement coupé → aucun envoi, canal coupé → canal retiré, SMS sans numéro → retiré. ⚠️ Les **codes de vérification et la 2FA en sont exclus** : un réglage capable de verrouiller l'accès à la plateforme n'a pas sa place dans une interface d'administration. **Contenu** : CRUD complet des pages et de la FAQ (le backend existait depuis B13.4, sans écran). **Référentiels** : les **villes** deviennent maintenables par l'équipe (le référentiel était figé depuis les seeders) ; les suppressions sont refusées tant qu'un bien ou un compte est rattaché, et un département non vide est protégé du `cascadeOnDelete` — sans quoi on effacerait des localisations, ou des dizaines de communes, sans le moindre message. **Écart CDC signalé, non comblé** : les **catégories** sont des enums PHP qui portent la logique métier ; l'écran les affiche en lecture seule et l'explique.

- [x] **Shell back-office dédié** (`/back-office`) + **connexion 2FA** (login → saisie du code e-mail) + redirection des comptes staff. — _F7.1.e._
- [x] Écran **Vue d'ensemble** (KPIs `GET /admin/dashboard` : files de validation, activité du jour, alertes, revenus, indicateurs). — _F7.1.e._
- [x] Écran **Équipe** (annuaire des employés, enrôlement d'un agent/admin avec invitation, rôle/statut). — _F7.1.f._
- [x] Écran **Permissions** (matrice de délégation par agent : 12 cases-dossiers groupées, gouvernance réservée au super_admin). — _F7.1.g._
- [x] Écran **Pointeuse** (pointer entrée/sortie perso + feuille de présence mensuelle d'équipe + détail + export CSV). — _F7.1.h._
- [x] **2FA** obligatoire pour les comptes admin/super_admin (OTP e-mail, `POST /auth/two-factor`) + jeton back-office à expiration courte (8 h) — _F7.1.d (back) + F7.1.e (écran)._
- [x] Écran **Validation** (file d'approbation générique `GET /admin/queue` + `PATCH /admin/validate/{type}/{id}` : onglets biens/véhicules/expériences/prestataires avec compteurs, **déposant identifié** (nom + e-mail/téléphone), valider/refuser avec motif). Enrichissement backend : `owner` joint à chaque entrée de la file (helper `OwnerEntry`, eager-loading). — _F7.2.a._
- [x] **Correctif SSR** : `/back-office` basculé en `RenderMode.Client` (comme les autres espaces privés) — le rafraîchissement d'une page du back-office ne déconnecte plus (le guard s'exécute côté client, là où la session existe). — _F7.2.a._
- [x] Écran **Catalogues** (supervision `GET /admin/properties|vehicles|experiences` : onglets Biens/Véhicules/Expériences, **tous statuts**, filtres statut + recherche, pagination, lecture seule). — _F7.2.b._
- [x] Écran **Nuitées** (exploitation `GET /admin/stays/calendar` + `PATCH /admin/stay-bookings/{id}/check-in|check-out|housekeeping` : calendrier des séjours, filtre par période, **check-in / check-out**, suivi du **ménage** À faire → En cours → Fait). Correctif du `DemoSeeder` au passage : les nuitées de démo sont posées sur des logements meublés dédiés (plus de « Villa à vendre » dans le calendrier). — _F7.2.c._
- [x] Écran **Paiements** (supervision `GET /admin/payments` : filtres statut + référence ; **confirmation manuelle** d'un règlement Wave/OM `POST /admin/payments/{id}/confirm` — Phase 1 du CDC ; **remboursement** total/partiel `POST /admin/payments/{id}/refund`). `DemoSeeder` enrichi de paiements de démo (manuels à confirmer, encaissés remboursables, remboursé). — _F7.2.d._
- [x] Écran **Dossiers** (supervision `GET /admin/construction-requests` + `GET /admin/mandates` : 2 onglets **lecture seule** — demandes de **construction** (objectif, budget, coût estimé, avancement rapports/jalons, statut) et **mandats de gestion locative** (bien, propriétaire, commission, loyers payés/impayés, dépenses, reversements, incidents, statut). Enrichissement backend : `milestones_count` + compteurs bruts `rents/incidents/expenses/payouts` surfacés dans les Resources. — _F7.2.e._
- [x] Écran **Comptes & documents** (annuaire `GET /admin/users` filtrable rôle/statut/recherche → **fiche détaillée** `GET /admin/users/{id}` : identité, profil, localisation, pilotage **rôle**/**statut**/**désactivation** `PATCH /admin/users/{id}`, **demande de pièce** `POST …/request-document`, **pièces déposées** (KYC) et **historique** du compte (journal d'audit Spatie). Onglet **Documents** : vue transverse `GET /admin/documents` — KYC / documents de biens / certifications / preuves de reversement, lecture seule. Ajouts backend : endpoint `show` (avec pièces + historique). — _F7.2.f ; **couvre les modules CDC §6 Utilisateurs et Documents**._
- [ ] Écran Tableau de bord (demandes du jour, revenus estimés, biens en attente, prestataires à valider, alertes, KPI). <!-- socle livré en Vue d'ensemble (F7.1.e) ; à enrichir. -->
- [x] Écran Utilisateurs (liste, rôles, statut, documents, historique, désactivation). — _F7.2.f (comptes publics ; volet équipe/permissions déjà en F7.1.f/g)._
- [ ] Écran Biens immobiliers (créer, modifier, valider, publier, archiver, attribuer). <!-- validation/publication livrée dans l'écran Validation (F7.2.a) ; reste la supervision catalogue (F7.2.b). -->
- [x] Écran Nuitées (calendrier, check-in/check-out, ménage). — _F7.2.c ; caution/disponibilité gérées côté catalogue/réservation._
- [ ] Écran Gestion locative (contrats, loyers, incidents, dépenses, reversements, rapport mensuel). <!-- supervision lecture seule des mandats livrée en F7.2.e ; reste le pilotage détaillé. -->
- [ ] Écran Construction (devis, prestataires BTP, jalons chantier, rapports photo/vidéo). <!-- supervision lecture seule des demandes livrée en F7.2.e ; reste le pilotage détaillé. -->
- [x] Écran Dossiers (supervision transverse construction + mandats). — _F7.2.e (lecture seule)._
- [x] Écran **Mobilité** (onglet **Flotte** : `GET /admin/vehicles` tous statuts, filtres catégorie / statut / **avec-sans chauffeur** / recherche, colonne **conformité** (assurance + identité du chauffeur pour le motorisé ; gilets + météo + agrément pour la pirogue) avec le détail des manquements en infobulle, capacité, tarif + caution, prestataire à joindre ; onglet **Trajets programmés** : `GET /admin/mobility-services` tous statuts, filtres nature / statut / **période de départ** / recherche, **remplissage** de chaque départ (jauge places prises / restantes, « Complet ») et véhicule affecté). Ajouts backend : endpoint `GET /admin/mobility-services` (`withSum` des réservations non annulées → aucun N+1) + `AdminVehicleResource` / `AdminMobilityServiceResource` (les champs de contrôle restent hors du catalogue public). Lecture seule : la décision reste dans l'écran Validation. — _F7.2.j ; **couvre le module CDC §6 Mobilité**._
- [x] Écran **Tourisme** (3 onglets). **Circuits** : `GET /admin/experiences` tous statuts, **capacité groupe** en jauge (places prises / restantes) et **programme** rendu par les *inclusions* du circuit, filtres statut / destination / recherche. **Destinations** : `GET /admin/tourism/destinations` — vue **agrégée** (circuits, publiés vs en attente, capacité cumulée, fourchette de prix), avec chaînage « Voir les circuits » vers l'onglet Circuits filtré. **Guides & restaurants** : `GET /admin/providers?category=guide,restauration` (filtre `category` multi-valeurs ajouté au backend). Ajouts backend : endpoint `GET /admin/tourism/destinations` + `AdminExperienceResource`. ⚠️ Écart CDC signalé à l'écran : **aucun lien guide nommé ↔ circuit** n'existe en base (guides et restaurants ne sont que des catégories de prestataires + des drapeaux d'inclusion). — _F7.2.k ; **couvre le module CDC §6 Tourisme**, hors affectation nominative des guides._
- [x] Écran **Team building** (file des demandes entreprises → fiche : composition du devis pack ligne à ligne + envoi, affectation des prestataires créant une mission Pro rattachée). — _F7.2.h._
- [x] Écran **Diaspora** (file **priorisée** des dossiers à distance + fiche de pilotage : re-priorisation, agent dédié explicite ou auto, progression/clôture du statut, rapports de suivi). — _F7.2.i._
- [ ] Écran Paiements (acomptes, soldes, commissions, statuts, remboursements, export comptable).
- [x] Écran Documents (KYC, documents de biens, certifications prestataires, preuves de reversement). — _F7.2.f (vue transverse `GET /admin/documents`, lecture seule)._
- [x] Écran **Avis et qualité** (onglet **Avis à modérer** : file `GET /admin/reviews` défaut `en_attente` + publier/rejeter `PATCH /reviews/{id}/moderate` ; onglet **Prestataires** : liste `GET /admin/providers` avec note agrégée + **avertir**/**suspendre** `PATCH /providers/{id}/warn|suspend`, motif obligatoire, seuil → suspension d'office). Ajouts backend : endpoints `GET /admin/reviews` (garde `moderer:avis`) + `GET /admin/providers` (garde `valider:prestataire`). Incidents = renvoi vers l'écran Dossiers. — _F7.2.g._
- [x] Écran **Paramètres & contenu** (4 onglets). **Réglages** : `GET`/`PATCH /admin/settings` — commissions & marges, coordonnées publiques, et le **barème du simulateur de construction** (les « tarifs » du CDC) aplati en champs éditables ; seules les clés réellement modifiées sont envoyées, pour ne pas transformer chaque valeur par défaut en surcharge. **Notifications** : coupure des canaux (SMS facturé, e-mail) et interrupteur **par événement** — réellement branché sur les `via()` des 12 notifications d'exploitation via `NotificationSettings`, ⚠️ les codes de sécurité / 2FA en sont **volontairement exclus** (les couper condamnerait l'accès). **Contenu** : CRUD complet des **pages** éditoriales et de la **FAQ**. **Référentiels** : les **villes** — arborescence région → département → communes avec création / renommage / suppression, la suppression étant **refusée (409)** dès qu'un bien ou un compte est rattaché (les FK sont en `nullOnDelete` / `cascadeOnDelete` : sans ce garde-fou, on perdrait des localisations en silence) — et les **catégories** en lecture seule. Réglages **réseaux sociaux** (`social.facebook|instagram|tiktok|linkedin|youtube`) dans la carte Général & contact, **consommés par le pied de page public** via `GET /contact-info` : un réseau vide est simplement masqué (jamais de lien mort), et une adresse incomplète est refusée (422). Ajouts backend : `AdminGeoController` (8 routes), réglages `notifications.*` + `social.*`, catalogue `NotificationEvent`. ⚠️ **Écart CDC signalé, non comblé** : les catégories sont des **enums PHP** qui pilotent la logique métier (validation, commissions, filtres) — les rendre éditables demanderait de sortir cette logique du code, hors périmètre ; l'écran l'affiche explicitement. — _F7.2.l ; **couvre le module CDC §6 Paramètres**, hors édition des catégories._
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
- [x] Un dossier diaspora peut être créé, suivi et enrichi de rapports. — _F3.8 : rubrique « Projets diaspora » de l'espace client (`/mon-espace/diaspora`) — création (`POST /diaspora-projects`), liste (`/mine`) et détail + chronologie des rapports (`/{id}/reports`)._
- [x] Une entreprise peut demander un pack groupe avec participants, lieu, dates et budget. — _F6 : espace entreprise (`/espace-entreprise`)._
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
