# Référence des endpoints — API Kaikun 360

Documentation technique de l'API REST (backend Laravel). Ce document recense
**les 261 endpoints** exposés sous le préfixe `/api/v1`, groupés par domaine, avec
leur niveau d'accès et le contrôleur responsable.

Il complète :
- `app/Support/README.md` — contrat d'API (enveloppe, erreurs, CORS, rate limit, cache) ;
- `PERFORMANCE.md` — durcissement & performance (B17) ;
- `CONFIDENTIALITE.md` — RGPD & rétention des données ;
- les sous-READMEs de modules (`app/Modules/<Module>/README.md`) — logique métier.

> **Régénérer / vérifier cette liste** : la source de vérité reste
> `php artisan route:list`. Ce document en est une vue annotée et doit être
> resynchronisé si des routes sont ajoutées.

## Conventions transverses

- **Base** : toutes les routes sont préfixées par `/api/v1`. Un endpoint de santé
  `GET /api/v1/version` renvoie la version courante.
- **Format** : requêtes et réponses en JSON (`Accept: application/json`).
- **Authentification** : jeton **Bearer** (Laravel Sanctum) obtenu via
  `POST /auth/login` ou `POST /auth/register`. En-tête `Authorization: Bearer <token>`.
- **Enveloppe de succès** : `{ "data": … }` (ou enveloppe paginée native
  `{ data, links, meta }` pour les listes). Voir `app/Support/README.md`.
- **Format d'erreur** : `{ "message": …, "errors": { champ: [messages] } }`.
  Codes : `401` non authentifié, `403` non autorisé, `404` introuvable,
  `422` validation, `429` quota dépassé, `502` erreur d'un service externe (PSP).
- **Pagination** : paramètres `?page=` et `?per_page=` (plafonné, défaut 15/20
  selon l'endpoint).
- **Limitation de débit** : `throttle:api` = 60 req/min ; limiteurs renforcés sur
  l'authentification (10/min par IP) et le paiement (15/min par utilisateur).
- **Colonne « Accès »** :
  - `public` — aucune authentification requise ;
  - `auth` — jeton Sanctum valide requis ;
  - `vérifié` — compte au moins partiellement vérifié (email **ou** téléphone),
    via le middleware `EnsureAccountVerified` ;
  - `` `can:<permission>` `` — permission fine (Spatie) requise (le `super_admin`
    contourne toutes les permissions via `Gate::before`) ;
  - `URL signée` — lien temporaire signé (téléchargement de document privé).

## Ressources (payloads)

Les formes JSON détaillées sont portées par les **API Resources** Laravel
(`app/Http/Resources` et `app/Modules/*/Http/Resources`) : `PropertyResource`,
`StayResource`, `VehicleResource`, `MobilityServiceResource`, `ExperienceResource`,
`ServiceRequestResource`, `QuoteResource`, `BookingResource`, `PaymentResource`,
`UserResource`, `ProfileResource`, `ReviewResource`, `MediaResource`,
`ProviderResource`, `MandateResource`, etc. Ces classes font foi pour les modèles
TypeScript miroir côté frontend Angular (phase F0).

---

## Catalogue des endpoints

### Authentification

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| POST | `/auth/login` | public | `AuthController@login` |
| POST | `/auth/google` | public | `AuthController@google` |
| POST | `/auth/logout` | auth | `AuthController@logout` |
| POST | `/auth/password/forgot` | public | `PasswordResetController@forgot` |
| POST | `/auth/password/reset` | public | `PasswordResetController@reset` |
| POST | `/auth/register` | public | `AuthController@register` |
| POST | `/auth/two-factor` | public (jeton de session courte) | `AuthController@twoFactor` |
| POST | `/auth/verify` | auth | `VerificationController@verify` |
| POST | `/auth/verify/send` | auth | `VerificationController@send` |

### Compte & profil

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| DELETE | `/users/me` | auth | `UserController@destroy` |
| GET | `/users/me` | auth | `UserController@me` |
| PATCH | `/users/me` | auth | `UserController@update` |
| PATCH | `/users/me/password` | auth | `UserController@updatePassword` |
| GET | `/users/me/documents` | auth | `DocumentController@index` |
| POST | `/users/me/avatar` | auth | `AvatarController@store` |
| DELETE | `/users/me/avatar` | auth | `AvatarController@destroy` |
| POST | `/users/me/documents` | auth | `DocumentController@store` |
| GET | `/users/me/documents/{document}/download` | URL signée | `DocumentController@download` |

### Immo — biens

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| GET | `/properties` | public | `PropertyCatalogController@index` |
| POST | `/properties` | auth + vérifié | `PropertyManagementController@store` |
| GET | `/properties/compare` | public | `PropertyCatalogController@compare` |
| GET | `/properties/mine` | auth | `PropertyManagementController@mine` |
| GET | `/properties/mine/{property}` | auth | `PropertyManagementController@show` |
| GET | `/properties/{id}` | public | `PropertyCatalogController@show` |
| PATCH | `/properties/{property}` | auth | `PropertyManagementController@update` |
| PATCH | `/properties/{property}/approve` | auth + `can:valider:bien` | `PropertyValidationController@approve` |
| GET | `/properties/{property}/documents` | auth | `PropertyManagementController@listDocuments` |
| POST | `/properties/{property}/documents` | auth | `PropertyManagementController@storeDocument` |
| DELETE | `/properties/{property}/documents/{document}` | auth | `PropertyManagementController@deleteDocument` |
| GET | `/properties/{property}/documents/{document}/download` | URL signée | `PropertyManagementController@downloadDocument` |
| PATCH | `/properties/{property}/reject` | auth + `can:valider:bien` | `PropertyValidationController@reject` |

### Stay — nuitées

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| GET | `/stays` | public | `StayCatalogController@index` |
| GET | `/stays/{id}` | public | `StayCatalogController@show` |
| GET | `/stays/{id}/availability` | public | `StayBookingController@availability` |
| POST | `/stays/{id}/bookings` | auth + vérifié | `StayBookingController@store` |
| PUT | `/properties/{property}/stay` | auth + vérifié | `StayManagementController@upsert` |
| DELETE | `/properties/{property}/stay` | auth | `StayManagementController@destroy` |

> Les deux dernières routes gèrent la config « nuitées » d'un bien par son
> **propriétaire** (F4.3) : activer/paramétrer (upsert) ou retirer le mode nuitées.
> Autorisées via la `PropertyPolicy` (propriétaire du bien ou admin).

### Explore — expériences

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| GET | `/experiences` | public | `ExperienceCatalogController@index` |
| POST | `/experiences` | auth + vérifié | `ExperienceManagementController@store` |
| PATCH | `/experiences/bookings/{booking}/cancel` | auth | `ExperienceBookingController@cancel` |
| GET | `/experiences/mine` | auth | `ExperienceManagementController@mine` |
| PATCH | `/experiences/{experience}` | auth (policy `update`) | `ExperienceManagementController@update` |
| DELETE | `/experiences/{experience}` | auth (policy `update`) | `ExperienceManagementController@destroy` |
| PATCH | `/experiences/{experience}/approve` | auth + `can:valider:experience` | `ExperienceValidationController@approve` |
| PATCH | `/experiences/{experience}/reject` | auth + `can:valider:experience` | `ExperienceValidationController@reject` |
| GET | `/experiences/{id}` | public | `ExperienceCatalogController@show` |
| GET | `/experiences/{id}/availability` | public | `ExperienceBookingController@availability` |
| POST | `/experiences/{id}/bookings` | auth + vérifié | `ExperienceBookingController@store` |

### Mobility — véhicules

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| GET | `/vehicles` | public | `VehicleCatalogController@index` |
| POST | `/vehicles` | auth + vérifié | `VehicleManagementController@store` |
| PATCH | `/vehicles/bookings/{booking}/cancel` | auth | `VehicleBookingController@cancel` |
| GET | `/vehicles/mine` | auth | `VehicleManagementController@mine` |
| GET | `/vehicles/{id}` | public | `VehicleCatalogController@show` |
| POST | `/vehicles/{id}/bookings` | auth + vérifié | `VehicleBookingController@store` |
| PATCH | `/vehicles/{vehicle}` | auth | `VehicleManagementController@update` |
| DELETE | `/vehicles/{vehicle}` | auth (policy `update`) | `VehicleManagementController@destroy` |
| PATCH | `/vehicles/{vehicle}/approve` | auth + `can:valider:vehicule` | `VehicleValidationController@approve` |
| PATCH | `/vehicles/{vehicle}/reject` | auth + `can:valider:vehicule` | `VehicleValidationController@reject` |

### Mobility — services

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| GET | `/mobility-services` | public | `MobilityServiceController@index` |
| GET | `/mobility-services/mine` | auth | `MobilityServiceManagementController@mine` |
| GET | `/mobility-services/{id}` | public | `MobilityServiceController@show` |
| POST | `/mobility-services` | auth + vérifié (policy `create`) | `MobilityServiceManagementController@store` |
| PATCH | `/mobility-services/{mobility_service}` | auth (policy `update`) | `MobilityServiceManagementController@update` |
| DELETE | `/mobility-services/{mobility_service}` | auth (policy `update`) | `MobilityServiceManagementController@destroy` |
| POST | `/mobility-services/{id}/bookings` | auth + vérifié | `MobilityServiceBookingController@store` |

> **F8.23 — les départs programmés deviennent écrivables.** La table était en
> **lecture seule depuis B7.2** : le catalogue public `/mobilite` ne pouvait
> être alimenté que par le seeder. La validation passe par la file générique du
> back-office sous le type **`mobility_service`** (`GET /admin/queue?type=mobility_service`,
> `PATCH /admin/validate/mobility_service/{id}`), avec la permission
> `valider:vehicule` — aucune permission neuve.

### Build — construction

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| POST | `/construction-requests` | auth | `ConstructionRequestController@store` |
| GET | `/construction-requests/mine` | auth | `ConstructionRequestController@mine` |
| POST | `/construction-requests/simulate` | public | `ConstructionRequestController@simulate` |
| GET | `/construction-requests/{constructionRequest}` | auth | `ConstructionRequestController@show` |
| GET | `/construction-requests/{constructionRequest}/reports` | auth | `ConstructionRequestController@reports` |
| POST | `/construction-requests/{constructionRequest}/reports` | auth + `can:gerer:chantiers` | `ConstructionReportController@store` |
| POST | `/construction-requests/{constructionRequest}/milestones` | auth + `can:gerer:chantiers` | `ConstructionMilestoneController@store` |
| PUT | `/construction-requests/{constructionRequest}/milestones/reorder` | auth + `can:gerer:chantiers` | `ConstructionMilestoneController@reorder` |
| PATCH | `/construction-milestones/{milestone}` | auth + `can:gerer:chantiers` | `ConstructionMilestoneController@update` |
| DELETE | `/construction-milestones/{milestone}` | auth + `can:gerer:chantiers` | `ConstructionMilestoneController@destroy` |
| GET | `/construction-requests/{constructionRequest}/quotes` | auth (policy `view`) | `ConstructionQuoteController@index` |
| POST | `/construction-requests/{constructionRequest}/quotes` | auth + `can:gerer:chantiers` | `ConstructionQuoteController@compose` |
| PATCH | `/construction-quotes/{quote}/send` | auth + `can:gerer:chantiers` | `ConstructionQuoteController@send` |
| PATCH | `/construction-quotes/{quote}/accept` | auth (policy `respond` — client) | `ConstructionQuoteController@accept` |
| PATCH | `/construction-quotes/{quote}/refuse` | auth (policy `respond` — client) | `ConstructionQuoteController@refuse` |
| GET | `/construction-requests/{constructionRequest}/assignments` | auth (policy `view`) | `ConstructionAssignmentController@index` |
| POST | `/construction-requests/{constructionRequest}/assignments` | auth + `can:gerer:chantiers` | `ConstructionAssignmentController@store` |

### Diaspora

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| GET | `/diaspora-projects` | auth + `can:consulter:dashboard-admin` | `DiasporaProjectController@index` |
| POST | `/diaspora-projects` | auth | `DiasporaProjectController@store` |
| GET | `/diaspora-projects/mine` | auth | `DiasporaProjectController@mine` |
| GET | `/diaspora-projects/{project}` | auth | `DiasporaProjectController@show` |
| PATCH | `/diaspora-projects/{project}` | auth (policy `update`) | `DiasporaProjectController@update` |
| PATCH | `/diaspora-projects/{project}/assign` | auth | `DiasporaAssignmentController@assign` |
| GET | `/diaspora-projects/{project}/reports` | auth | `DiasporaReportController@index` |
| POST | `/diaspora-projects/{project}/reports` | auth | `DiasporaReportController@store` |

### Team Building

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| PATCH | `/team-building-quotes/{quote}/accept` | auth | `TeamBuildingQuoteController@accept` |
| PATCH | `/team-building-quotes/{quote}/send` | auth | `TeamBuildingQuoteController@send` |
| GET | `/team-building-requests` | auth + `can:consulter:dashboard-admin` | `TeamBuildingRequestController@index` |
| POST | `/team-building-requests` | auth | `TeamBuildingRequestController@store` |
| GET | `/team-building-requests/mine` | auth | `TeamBuildingRequestController@mine` |
| GET | `/team-building-requests/{teamBuildingRequest}` | auth | `TeamBuildingRequestController@show` |
| GET | `/team-building-requests/{teamBuildingRequest}/quotes` | auth | `TeamBuildingQuoteController@index` |
| POST | `/team-building-requests/{teamBuildingRequest}/quotes` | auth | `TeamBuildingQuoteController@compose` |
| GET | `/team-building-requests/{teamBuildingRequest}/assignments` | auth (policy `manage`) | `TeamBuildingAssignmentController@index` |
| POST | `/team-building-requests/{teamBuildingRequest}/assignments` | auth (policy `manage`) | `TeamBuildingAssignmentController@store` |

### Pro — prestataires

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| POST | `/providers` | auth + vérifié | `ProviderRegistrationController@store` |
| GET | `/providers/mine` | auth | `ProviderRegistrationController@mine` |
| PUT | `/providers/mine` | auth | `ProviderProfileController@update` |
| POST | `/providers/certifications` | auth | `ProviderProfileController@storeCertification` |
| GET | `/providers/certifications/{certification}/download` | URL signée | `ProviderProfileController@downloadCertification` |
| DELETE | `/providers/certifications/{certification}` | auth | `ProviderProfileController@destroyCertification` |
| GET | `/providers/availability` | auth | `ProviderAvailabilityController@show` |
| PUT | `/providers/availability/weekly` | auth | `ProviderAvailabilityController@updateWeekly` |
| POST | `/providers/availability/unavailability` | auth | `ProviderAvailabilityController@storeUnavailability` |
| DELETE | `/providers/availability/unavailability/{unavailability}` | auth | `ProviderAvailabilityController@destroyUnavailability` |
| GET | `/providers/reviews` | auth | `ProviderReviewController@index` |
| POST | `/providers/{provider}/missions` | auth | `ProviderMissionController@store` |
| PATCH | `/providers/{provider}/reject` | auth + `can:valider:prestataire` | `ProviderValidationController@reject` |
| PATCH | `/providers/{provider}/suspend` | auth + `can:valider:prestataire` | `ProviderValidationController@suspend` |
| PATCH | `/providers/{provider}/validate` | auth + `can:valider:prestataire` | `ProviderValidationController@validate` |
| PATCH | `/providers/{provider}/warn` | auth + `can:valider:prestataire` | `ProviderValidationController@warn` |

### Pro — missions

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| GET | `/provider-missions/mine` | auth | `ProviderMissionController@mine` |
| GET | `/provider-missions/earnings` | auth | `ProviderMissionController@earnings` |
| PATCH | `/provider-missions/{mission}/{action}` | auth | `ProviderMissionController@transition` |

### Manage — gestion locative

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| GET | `/manage/dashboard` | auth | `ManageController@dashboard` |
| PATCH | `/manage/incidents/{incident}/resolve` | auth + `can:gerer:gestion-locative` | `MandateManagementController@resolveIncident` |
| POST | `/manage/mandates` | auth + `can:gerer:gestion-locative` | `MandateManagementController@storeMandate` |
| GET | `/manage/mandates/mine` | auth | `ManageController@mine` |
| GET | `/manage/mandates/{mandate}` | auth | `ManageController@show` |
| POST | `/manage/mandates/{mandate}/expenses` | auth + `can:gerer:gestion-locative` | `MandateManagementController@storeExpense` |
| POST | `/manage/mandates/{mandate}/incidents` | auth + `can:gerer:gestion-locative` | `MandateManagementController@storeIncident` |
| POST | `/manage/mandates/{mandate}/payouts` | auth + `can:gerer:gestion-locative` | `MandateManagementController@storePayout` |
| POST | `/manage/mandates/{mandate}/rents` | auth + `can:gerer:gestion-locative` | `MandateManagementController@storeRent` |
| GET | `/manage/mandates/{mandate}/report` | auth | `ManageController@report` |
| POST | `/manage/payouts/{payout}/pay` | auth + `can:gerer:gestion-locative` | `MandateManagementController@markPayoutPaid` |
| GET | `/manage/payouts/{payout}/proof` | **URL signée** (10 min) | `MandateManagementController@downloadPayoutProof` |

> **Le justificatif de reversement devient obligatoire (2026-08-06).**
> `owner_payouts.proof_path` existait depuis **B4.4** et **rien ne l'écrivait
> jamais** : le constat posait le statut et la date, sans aucune preuve — pendant
> que l'écran **Documents** du back-office (F7.4.c) prétendait compter les
> justificatifs de reversement, et affichait le *chemin de stockage* comme nom de
> fichier. Il comptait donc invariablement zéro.
>
> ⚠️ **La méthode passe de `PATCH` à `POST`** : la pièce voyage en
> `multipart/form-data`, que PHP ne décode que sur un POST (`$_FILES` reste vide
> sur un PATCH). Aligné sur `POST /admin/partner-payouts/{payout}/pay`
> (F8.16.a) — c'est le même acte, sortir de l'argent vers un partenaire.
>
> ⚠️ Le chemin de stockage n'est jamais exposé : `OwnerPayoutResource` sert
> `proof_url`, **URL signée de 10 minutes**, comme le KYC et les certifications.
| PATCH | `/manage/rents/{rent}/pay` | auth + `can:gerer:gestion-locative` | `MandateManagementController@markRentPaid` |

### Requests (transversal)

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| POST | `/requests` | auth | `RequestController@store` |
| GET | `/requests/my` | auth | `RequestController@my` |
| GET | `/requests/{serviceRequest}` | auth | `RequestController@show` |
| POST | `/requests/{serviceRequest}/quotes` | auth + `can:traiter:demandes` | `QuoteController@store` |
| PATCH | `/requests/{serviceRequest}/status` | auth + `can:traiter:demandes` | `RequestController@updateStatus` |

### Quotes (transversal)

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| GET | `/quotes/{quote}` | auth | `QuoteController@show` |
| PATCH | `/quotes/{quote}` | auth | `QuoteController@respond` |

### Bookings (transversal)

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| GET | `/bookings/my` | auth | `BookingController@my` |
| GET | `/bookings/{booking}` | auth | `BookingController@show` |

### Messagerie (transversal)

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| GET | `/messages` | auth | `MessageController@index` |
| POST | `/messages` | auth + `can:repondre:messages` | `MessageController@start` |
| POST | `/messages/support` | auth | `MessageController@startWithSupport` |
| GET | `/messages/unread-count` | auth | `MessageController@unreadCount` |
| GET | `/messages/{conversation}` | auth | `MessageController@show` |
| POST | `/messages/{conversation}/messages` | auth | `MessageController@store` |

> Socle **générique** (conversations à participants + messages), réutilisable par
> les espaces pro (F4/F5/F6). Accès **scopé** à l'utilisateur courant : un fil dont
> on n'est pas participant renvoie `404` (aucune fuite). Les non-lus se calculent
> par participant (`last_read_at` du pivot) ; `GET /messages/{conversation}` marque
> le fil comme lu. Chaque message notifie les autres participants (canal
> `database`, cf. Notifications). `POST /messages` dédoublonne les fils directs
> (mêmes deux participants, sans contexte).
>
> **F8.12 — support pivot.** Le client n'ouvre PAS un fil vers le compte de son
> choix : il écrit au support via `POST /messages/support` (aucun `recipient_id`),
> et le serveur lui assigne un agent du vivier `repondre:messages`
> (`SupportAssignmentService`, le moins chargé d'abord ; `null` si le vivier est
> vide — le fil part quand même et attend dans « Non assignés »). Corps :
> `body`, `subject?`, et le dossier concerné `context_type?` (slug de la liste
> blanche `ConversationContext` : `demande`, `devis`, `reservation`, `bien`,
> `nuitee`, `vehicule`, `circuit`, `trajet`) + `context_id?`. Un dossier
> personnel n'est rattaché que s'il appartient à l'auteur — sinon le contexte est
> **ignoré** (le message part quand même). Réécrire à propos du même dossier
> **reprend** le fil ouvert. Tout nouveau message **rouvre** un fil clos.
> `POST /messages` (destinataire désigné) est depuis réservé à l'équipe.
>
> **F8.12.a — relève périodique.** `GET /messages/{conversation}` et
> `GET /admin/conversations/{conversation}` acceptent **`?after=<message_id>`** :
> la réponse ne porte alors que les messages d'identifiant supérieur. C'est ce
> qui permet à un fil ouvert de se tenir à jour (battement de 10 s côté écran)
> sans retélécharger l'historique. ⚠️ En relève **à vide** (aucun message plus
> récent), le serveur **ne met pas à jour `last_read_at`** — sinon chaque
> battement produirait une écriture en base pour ne rien changer.
>
> **F8.12.c — un tiers dans le fil.** `GET …/candidates` propose la personne
> rattachée au dossier (`ConversationContext::holder()` : propriétaire du bien,
> hôte de la nuitée via son bien, prestataire du véhicule / circuit / trajet,
> et pour une réservation le détenteur de ce qui est réservé) puis, avec
> `?search=`, une recherche **restreinte aux rôles propriétaire et
> prestataire**. `POST …/participants` le fait entrer (il voit **tout
> l'historique** et reçoit une notification), `DELETE …/participants/{user}` le
> sort — ni le demandeur ni l'agent responsable ne peuvent l'être (422), et les
> messages déjà écrits restent. ⚠️ **Masquage** : `ContactMasker` remplace les
> e-mails et les suites d'au moins **7 chiffres** par « ••• » dans le corps des
> messages **pour les lecteurs non-staff** ; l'équipe voit le texte entier (elle
> doit pouvoir arbitrer un litige). Sept chiffres et non six : un prix
> (« 250 000 ») ne doit pas être haché. Le filtre réduit la friction, **il ne
> verrouille rien** — la vraie protection contre la désintermédiation est
> contractuelle.

### Avis

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| GET | `/reviews` | public | `ReviewController@index` |
| GET | `/reviews/mine` | auth | `ReviewController@mine` |
| POST | `/reviews` | auth | `ReviewController@store` |
| PATCH | `/reviews/{review}/moderate` | auth | `ReviewController@moderate` |

> `/reviews` ne renvoie que les avis **publiés** ; `/reviews/mine` renvoie ceux
> de l'utilisateur connecté **y compris en attente de modération** (F8.15.a).
> Sans cette seconde route, l'écran « Donner mon avis » rouvrirait son
> formulaire à un client qui vient d'écrire, pour l'envoyer sur un 422.
>
> ⚠️ Le dépôt exige une réservation **terminée** (`ReviewPolicy`). Ce statut
> n'est atteint que par la tâche planifiée `reservations:cloturer` ou par le
> check-out d'un agent : sans cron en production, personne ne peut noter.

### Médias

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| POST | `/media/upload` | auth | `MediaController@store` |
| PATCH | `/media/{media}/primary` | auth | `MediaController@setPrimary` |
| DELETE | `/media/{media}` | auth | `MediaController@destroy` |

### Favoris (transversal, polymorphe)

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| GET | `/favorites` | auth | `FavoriteController@index` |
| GET | `/favorites/ids` | auth | `FavoriteController@ids` |
| POST | `/favorites` | auth | `FavoriteController@store` |
| DELETE | `/favorites/{type}/{id}` | auth | `FavoriteController@destroy` |

> Favoris **polymorphes** (tous univers) : `type` ∈ {property, stay, vehicle,
> experience, mobility} (voir `App\Support\Favoritables`). `POST /favorites`
> `{ type, id }` n'accepte qu'un élément **publié / réservable** (404 sinon),
> idempotent ; `GET /favorites` liste tous univers confondus (élément embarqué,
> même forme que le catalogue) ; `GET /favorites/ids` renvoie les ids favoris
> regroupés par type (marquage des cœurs du catalogue sans requête par carte).
> Remplace les anciennes routes `/properties/{id}/favorite` du module Immo.

### Contenu éditorial

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| GET | `/faqs` | public | `FaqController@published` |
| GET | `/pages/{page}` | public | `PageController@show` |
| GET | `/contact-info` | public | `ContactController@info` |
| POST | `/contact` | public (throttle 10/min) | `ContactController@store` |

### Assistant (F10 — hors CDC)

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| POST | `/assistant/messages` | public, auth **facultative** (throttle 12/min) | `AssistantController@message` |

Endpoint **unique** pour toute la plateforme et les 8 rôles : ce n'est pas la route qui
varie selon l'appelant, c'est la **trousse à outils** que le `ToolRegistry` lui compose.
Sans état — l'historique voyage avec la requête (`history`, 10 tours max ; `message`,
500 caractères max). Réponse : `{ data: { reply: { text, items, actions, tool } } }`.

L'assistant **ne réalise aucune écriture** : il renvoie des actions (`link`, `support`,
`contact`) que le frontend transforme en boutons, lesquels appellent les endpoints métier
existants. Voir [`app/Modules/Assistant/README.md`](app/Modules/Assistant/README.md).

### Référentiel géographique

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| GET | `/regions` | public | `GeoController@regions` |
| GET | `/departments` | public | `GeoController@departments` |
| GET | `/communes` | public | `GeoController@communes` |

### Paiement

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| POST | `/payments/initiate` | auth + vérifié | `PaymentController@initiate` |
| POST | `/payments/webhook` | public (signé HMAC) | `PaymentWebhookController@handle` |

> Le webhook PayTech est **public** au sens middleware mais protégé par une
> **signature HMAC-SHA256** vérifiée en tête de contrôleur (rejet `401` sinon).
>
> `POST /payments/initiate` accepte un champ `mode` : `paytech` (défaut, renvoie
> une URL de redirection PSP) ou `manuel` (Phase 1 du cahier des charges : aucun
> appel PSP, renvoie les instructions de règlement Wave/Orange Money ; la
> confirmation se fait ensuite via `POST /admin/payments/{payment}/confirm`).
>
> ⚠️ **`POST /payments/initiate` exige un `booking_id`** — c'est la seule chose
> qu'un paiement sache régler. Les **trois** familles de devis produisent donc
> une réservation à l'acceptation, et leurs endpoints renvoient un objet
> `booking` en plus du `quote` (F8.11 puis F8.14) :
> `PATCH /quotes/{quote}` (générique), `PATCH /construction-quotes/{quote}/accept`
> (chantier) et `PATCH /team-building-quotes/{quote}/accept` (séminaire).
> Le devis accepté est **lui-même** la cible polymorphe (`bookable_type`) : le
> sur-mesure n'a aucune fiche au catalogue à désigner, et `bookings` est
> polymorphe depuis B3.3 — aucune migration. ⚠️ Pour le **chantier** et le
> **team building**, la commission de la réservation est la **marge déjà
> chiffrée dans le devis** (`margin_xof`), et non le taux commun de
> `CommissionCalculator` : le total signé par le client la contient déjà.

### Notifications

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| GET | `/users/me/notifications` | auth | `NotificationController@index` |
| GET | `/users/me/notifications/unread-count` | auth | `NotificationController@unreadCount` |
| PATCH | `/users/me/notifications/read-all` | auth | `NotificationController@markAllAsRead` |
| PATCH | `/users/me/notifications/{notification}/read` | auth | `NotificationController@markAsRead` |
| GET | `/whatsapp/link` | public | `WhatsAppLinkController@generate` |

### Back-office (Admin)

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| GET | `/admin/attendance` | auth + `can:gerer:utilisateurs` | `AttendanceController@sheet` |
| POST | `/admin/attendance/clock-in` | auth + `can:consulter:dashboard-admin` | `AttendanceController@clockIn` |
| POST | `/admin/attendance/clock-out` | auth + `can:consulter:dashboard-admin` | `AttendanceController@clockOut` |
| GET | `/admin/attendance/me` | auth + `can:consulter:dashboard-admin` | `AttendanceController@me` |
| GET | `/admin/communes` | auth + `can:gerer:parametres` | `AdminGeoController@communes` |
| POST | `/admin/communes` | auth + `can:gerer:parametres` | `AdminGeoController@storeCommune` |
| PATCH | `/admin/communes/{commune}` | auth + `can:gerer:parametres` | `AdminGeoController@updateCommune` |
| DELETE | `/admin/communes/{commune}` | auth + `can:gerer:parametres` | `AdminGeoController@destroyCommune` |
| GET | `/admin/construction-requests` | auth + `can:consulter:dashboard-admin` | `AdminDossierController@constructionRequests` |
| GET | `/admin/dashboard` | auth + `can:consulter:dashboard-admin` | `AdminDashboardController@show` |
| GET | `/admin/documents` | auth + `can:gerer:utilisateurs` | `AdminDocumentController@index` |
| POST | `/admin/departments` | auth + `can:gerer:parametres` | `AdminGeoController@storeDepartment` |
| PATCH | `/admin/departments/{department}` | auth + `can:gerer:parametres` | `AdminGeoController@updateDepartment` |
| DELETE | `/admin/departments/{department}` | auth + `can:gerer:parametres` | `AdminGeoController@destroyDepartment` |
| GET | `/admin/geography` | auth + `can:gerer:parametres` | `AdminGeoController@tree` |
| GET | `/admin/experiences` | auth + `can:consulter:dashboard-admin` | `AdminCatalogController@experiences` |
| GET | `/admin/experiences/{experience}` | auth + `can:consulter:dashboard-admin` | `AdminCatalogController@experience` |
| GET | `/admin/faqs` | auth + `can:gerer:parametres` | `FaqController@index` |
| POST | `/admin/faqs` | auth + `can:gerer:parametres` | `FaqController@store` |
| DELETE | `/admin/faqs/{faq}` | auth + `can:gerer:parametres` | `FaqController@destroy` |
| PATCH | `/admin/faqs/{faq}` | auth + `can:gerer:parametres` | `FaqController@update` |
| GET | `/admin/contact-messages` | auth + `can:traiter:demandes` | `ContactController@index` |
| GET | `/admin/contact-messages/{contactMessage}` | auth + `can:repondre:messages` | `ContactController@show` |
| PATCH | `/admin/contact-messages/{contactMessage}` | auth + `can:traiter:demandes` | `ContactController@update` |
| GET | `/admin/mandates` | auth + `can:consulter:dashboard-admin` | `AdminDossierController@mandates` |
| GET | `/admin/mobility-services` | auth + `can:consulter:dashboard-admin` | `AdminCatalogController@mobilityServices` |
| GET | `/admin/mobility-services/{service}` | auth + `can:consulter:dashboard-admin` | `AdminCatalogController@mobilityService` |
| GET | `/admin/pages` | auth + `can:gerer:parametres` | `PageController@index` |
| POST | `/admin/pages` | auth + `can:gerer:parametres` | `PageController@store` |
| DELETE | `/admin/pages/{page}` | auth + `can:gerer:parametres` | `PageController@destroy` |
| PATCH | `/admin/pages/{page}` | auth + `can:gerer:parametres` | `PageController@update` |
| GET | `/admin/payments` | auth + `can:gerer:paiements` | `AdminPaymentController@index` |
| GET | `/admin/payments/{payment}` | auth + `can:gerer:paiements` | `AdminPaymentController@show` |
| POST | `/admin/payments/{payment}/confirm` | auth + `can:gerer:paiements` | `AdminPaymentController@confirm` |
| POST | `/admin/payments/{payment}/refund` | auth + `can:gerer:paiements` | `AdminPaymentController@refund` |
| GET | `/admin/partner-dues` | auth + `can:gerer:paiements` | `AdminPartnerPayoutController@dues` |
| GET | `/admin/partner-dues/beneficiaries` | auth + `can:gerer:paiements` | `AdminPartnerPayoutController@beneficiaries` |
| GET | `/admin/partner-payouts` | auth + `can:gerer:paiements` | `AdminPartnerPayoutController@index` |
| POST | `/admin/partner-payouts` | auth + `can:gerer:paiements` | `AdminPartnerPayoutController@store` |
| GET | `/admin/partner-payouts/{payout}` | auth + `can:gerer:paiements` | `AdminPartnerPayoutController@show` |
| POST | `/admin/partner-payouts/{payout}/pay` | auth + `can:gerer:paiements` | `AdminPartnerPayoutController@pay` |
| POST | `/admin/partner-payouts/{payout}/fail` | auth + `can:gerer:paiements` | `AdminPartnerPayoutController@fail` |
| GET | `/admin/partner-payouts/{payout}/proof` | **URL signée** (10 min) | `AdminPartnerPayoutController@proof` |

> **Reversements aux partenaires (F8.16.a).** Kaikun encaisse et commissionne sur
> tous les univers depuis F8.4 mais ne reversait qu'en gestion locative
> (`owner_payouts.mandate_id` est **non nullable**) : jusqu'ici, **si un hôte
> demandait ce qu'on lui devait, personne ne pouvait répondre**. Deux tables
> transversales comblent le trou — `partner_dues` (le registre, une ligne par
> service rendu, source **polymorphe** `Booking` ou `ProviderMission`) et
> `partner_payouts` (le versement, qui solde plusieurs dettes d'un même
> bénéficiaire).
>
> ⚠️ **Le serveur n'exécute aucun virement.** `POST .../pay` **constate** un
> paiement fait par ailleurs (Wave, Orange Money, virement) et exige un
> **justificatif** — la colonne `owner_payouts.proof_path` existe depuis B4.4 sans
> qu'aucun endpoint ne l'ait jamais écrite, on ne refait pas la promesse.
>
> ⚠️ **Garde `gerer:paiements` et non une permission neuve** : reverser, c'est
> sortir de l'argent, exactement ce que garde déjà cette permission de
> **gouvernance** (un agent purement opérationnel reçoit 403).
>
> ⚠️ La caution **n'entre jamais** dans l'assiette, la commission est **recopiée
> figée** depuis la source, et un **remboursement éteint** la dette encore vivante
> (si le virement est déjà parti, la ligne reste « payée » : l'écart est une
> créance à régler hors application).
| GET | `/admin/properties` | auth + `can:consulter:dashboard-admin` | `AdminCatalogController@properties` |
| PATCH | `/admin/properties/{property}` | auth + `can:valider:bien` | `AdminPropertyController@update` |
| PATCH | `/admin/properties/{property}/archive` | auth + `can:valider:bien` | `AdminPropertyController@archive` |
| PATCH | `/admin/properties/{property}/restore` | auth + `can:valider:bien` | `AdminPropertyController@restore` |
| GET | `/admin/providers` | auth + `can:valider:prestataire` | `AdminProviderController@index` |
| GET | `/admin/providers/{provider}` | auth + `can:valider:prestataire` | `AdminProviderController@show` |
| GET | `/admin/queue` | auth + `can:consulter:dashboard-admin` | `ValidationQueueController@index` |
| GET | `/admin/queue/{type}/{id}` | auth + `can:consulter:dashboard-admin` | `ValidationQueueController@show` |
| PATCH | `/admin/media/{media}/status` | auth + `can:consulter:dashboard-admin` | `MediaModerationController@update` |
| GET | `/admin/reference` | auth + `can:consulter:dashboard-admin` | `ReferenceController@index` |
| GET | `/admin/reports/export` | auth + `can:gerer:paiements` | `ReportExportController@export` |
| GET | `/admin/conversations` | auth + `can:repondre:messages` | `AdminConversationController@index` |
| GET | `/admin/conversations/{conversation}` | auth + `can:repondre:messages` | `AdminConversationController@show` |
| PATCH | `/admin/conversations/{conversation}` | auth + `can:repondre:messages` | `AdminConversationController@update` |
| GET | `/admin/conversations/{conversation}/candidates` | auth + `can:repondre:messages` | `AdminConversationController@candidates` |
| POST | `/admin/conversations/{conversation}/messages` | auth + `can:repondre:messages` | `AdminConversationController@reply` |
| POST | `/admin/conversations/{conversation}/participants` | auth + `can:repondre:messages` | `AdminConversationController@addParticipant` |
| DELETE | `/admin/conversations/{conversation}/participants/{user}` | auth + `can:repondre:messages` | `AdminConversationController@removeParticipant` |
| GET | `/admin/requests` | auth + `can:traiter:demandes` | `AdminRequestController@index` |
| GET | `/admin/requests/filters` | auth + `can:traiter:demandes` | `AdminRequestController@filters` |
| GET | `/admin/requests/{serviceRequest}` | auth + `can:traiter:demandes` | `AdminRequestController@show` |
| GET | `/admin/reviews` | auth + `can:moderer:avis` | `AdminReviewController@index` |
| GET | `/admin/reviews/{review}` | auth + `can:moderer:avis` | `AdminReviewController@show` |
| GET | `/admin/settings` | auth + `can:gerer:parametres` | `AdminSettingsController@index` |
| PATCH | `/admin/settings` | auth + `can:gerer:parametres` | `AdminSettingsController@update` |
| PATCH | `/admin/stay-bookings/{booking}/check-in` | auth + `can:gerer:nuitees` | `StayOperationsController@checkIn` |
| PATCH | `/admin/stay-bookings/{booking}/check-out` | auth + `can:gerer:nuitees` | `StayOperationsController@checkOut` |
| PATCH | `/admin/stay-bookings/{booking}/housekeeping` | auth + `can:gerer:nuitees` | `StayOperationsController@housekeeping` |
| PATCH | `/admin/stay-bookings/{booking}/caution` | auth + `can:gerer:nuitees` | `StayOperationsController@caution` |
| GET | `/admin/stays/calendar` | auth + `can:gerer:nuitees` | `StayOperationsController@calendar` |
| GET | `/admin/stay-bookings/{booking}` | auth + `can:gerer:nuitees` | `StayOperationsController@show` |
| GET | `/admin/tourism/destinations` | auth + `can:consulter:dashboard-admin` | `AdminCatalogController@tourismDestinations` |
| GET | `/admin/team` | auth + `can:gerer:utilisateurs` | `AdminTeamController@index` |
| POST | `/admin/team` | auth + `can:gerer:utilisateurs` | `AdminTeamController@store` |
| PATCH | `/admin/team/{member}` | auth + `can:gerer:utilisateurs` | `AdminTeamController@update` |
| GET | `/admin/team/{member}/permissions` | auth + `can:gerer:utilisateurs` (gouvernance super_admin) | `AdminTeamController@permissions` |
| PUT | `/admin/team/{member}/permissions` | auth + `can:gerer:utilisateurs` (gouvernance super_admin) | `AdminTeamController@syncPermissions` |
| GET | `/admin/users` | auth + `can:gerer:utilisateurs` | `AdminUserController@index` |
| GET | `/admin/users/{user}` | auth + `can:gerer:utilisateurs` | `AdminUserController@show` |
| PATCH | `/admin/users/{user}` | auth + `can:gerer:utilisateurs` | `AdminUserController@update` |
| POST | `/admin/users/{user}/request-document` | auth + `can:gerer:utilisateurs` | `AdminUserController@requestDocument` |
| PATCH | `/admin/validate/{type}/{id}` | auth + `can:consulter:dashboard-admin` | `ValidationQueueController@decide` |
| GET | `/admin/vehicles` | auth + `can:consulter:dashboard-admin` | `AdminCatalogController@vehicles` |
| GET | `/admin/vehicles/{vehicle}` | auth + `can:consulter:dashboard-admin` | `AdminCatalogController@vehicle` |

### Santé

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| GET | `/version` | public | `Closure` |
