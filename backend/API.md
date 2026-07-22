# Référence des endpoints — API Kaikun 360

Documentation technique de l'API REST (backend Laravel). Ce document recense
**les 168 endpoints** exposés sous le préfixe `/api/v1`, groupés par domaine, avec
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
| PATCH | `/vehicles/{vehicle}/approve` | auth + `can:valider:vehicule` | `VehicleValidationController@approve` |
| PATCH | `/vehicles/{vehicle}/reject` | auth + `can:valider:vehicule` | `VehicleValidationController@reject` |

### Mobility — services

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| GET | `/mobility-services` | public | `MobilityServiceController@index` |
| POST | `/mobility-services/{id}/bookings` | auth + vérifié | `MobilityServiceBookingController@store` |

### Build — construction

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| POST | `/construction-requests` | auth | `ConstructionRequestController@store` |
| GET | `/construction-requests/mine` | auth | `ConstructionRequestController@mine` |
| POST | `/construction-requests/simulate` | public | `ConstructionRequestController@simulate` |
| GET | `/construction-requests/{constructionRequest}` | auth | `ConstructionRequestController@show` |
| GET | `/construction-requests/{constructionRequest}/reports` | auth | `ConstructionRequestController@reports` |
| POST | `/construction-requests/{constructionRequest}/reports` | auth + `can:gerer:chantiers` | `ConstructionReportController@store` |

### Diaspora

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| GET | `/diaspora-projects` | auth + `can:consulter:dashboard-admin` | `DiasporaProjectController@index` |
| POST | `/diaspora-projects` | auth | `DiasporaProjectController@store` |
| GET | `/diaspora-projects/mine` | auth | `DiasporaProjectController@mine` |
| GET | `/diaspora-projects/{project}` | auth | `DiasporaProjectController@show` |
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

### Pro — prestataires

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| POST | `/providers` | auth + vérifié | `ProviderRegistrationController@store` |
| GET | `/providers/mine` | auth | `ProviderRegistrationController@mine` |
| POST | `/providers/{provider}/missions` | auth | `ProviderMissionController@store` |
| PATCH | `/providers/{provider}/reject` | auth + `can:valider:prestataire` | `ProviderValidationController@reject` |
| PATCH | `/providers/{provider}/suspend` | auth + `can:valider:prestataire` | `ProviderValidationController@suspend` |
| PATCH | `/providers/{provider}/validate` | auth + `can:valider:prestataire` | `ProviderValidationController@validate` |
| PATCH | `/providers/{provider}/warn` | auth + `can:valider:prestataire` | `ProviderValidationController@warn` |

### Pro — missions

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| GET | `/provider-missions/mine` | auth | `ProviderMissionController@mine` |
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
| PATCH | `/manage/payouts/{payout}/pay` | auth + `can:gerer:gestion-locative` | `MandateManagementController@markPayoutPaid` |
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
| POST | `/messages` | auth | `MessageController@start` |
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

### Avis

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| GET | `/reviews` | public | `ReviewController@index` |
| POST | `/reviews` | auth | `ReviewController@store` |
| PATCH | `/reviews/{review}/moderate` | auth | `ReviewController@moderate` |

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
| GET | `/admin/construction-requests` | auth + `can:consulter:dashboard-admin` | `AdminDossierController@constructionRequests` |
| GET | `/admin/dashboard` | auth + `can:consulter:dashboard-admin` | `AdminDashboardController@show` |
| GET | `/admin/documents` | auth + `can:gerer:utilisateurs` | `AdminDocumentController@index` |
| GET | `/admin/experiences` | auth + `can:consulter:dashboard-admin` | `AdminCatalogController@experiences` |
| GET | `/admin/faqs` | auth + `can:gerer:parametres` | `FaqController@index` |
| POST | `/admin/faqs` | auth + `can:gerer:parametres` | `FaqController@store` |
| DELETE | `/admin/faqs/{faq}` | auth + `can:gerer:parametres` | `FaqController@destroy` |
| PATCH | `/admin/faqs/{faq}` | auth + `can:gerer:parametres` | `FaqController@update` |
| GET | `/admin/contact-messages` | auth + `can:traiter:demandes` | `ContactController@index` |
| PATCH | `/admin/contact-messages/{contactMessage}` | auth + `can:traiter:demandes` | `ContactController@update` |
| GET | `/admin/mandates` | auth + `can:consulter:dashboard-admin` | `AdminDossierController@mandates` |
| GET | `/admin/pages` | auth + `can:gerer:parametres` | `PageController@index` |
| POST | `/admin/pages` | auth + `can:gerer:parametres` | `PageController@store` |
| DELETE | `/admin/pages/{page}` | auth + `can:gerer:parametres` | `PageController@destroy` |
| PATCH | `/admin/pages/{page}` | auth + `can:gerer:parametres` | `PageController@update` |
| GET | `/admin/payments` | auth + `can:gerer:paiements` | `AdminPaymentController@index` |
| POST | `/admin/payments/{payment}/confirm` | auth + `can:gerer:paiements` | `AdminPaymentController@confirm` |
| POST | `/admin/payments/{payment}/refund` | auth + `can:gerer:paiements` | `AdminPaymentController@refund` |
| GET | `/admin/properties` | auth + `can:consulter:dashboard-admin` | `AdminCatalogController@properties` |
| GET | `/admin/queue` | auth + `can:consulter:dashboard-admin` | `ValidationQueueController@index` |
| GET | `/admin/reference` | auth + `can:consulter:dashboard-admin` | `ReferenceController@index` |
| GET | `/admin/reports/export` | auth + `can:gerer:paiements` | `ReportExportController@export` |
| GET | `/admin/settings` | auth + `can:gerer:parametres` | `AdminSettingsController@index` |
| PATCH | `/admin/settings` | auth + `can:gerer:parametres` | `AdminSettingsController@update` |
| PATCH | `/admin/stay-bookings/{booking}/check-in` | auth + `can:gerer:nuitees` | `StayOperationsController@checkIn` |
| PATCH | `/admin/stay-bookings/{booking}/check-out` | auth + `can:gerer:nuitees` | `StayOperationsController@checkOut` |
| PATCH | `/admin/stay-bookings/{booking}/housekeeping` | auth + `can:gerer:nuitees` | `StayOperationsController@housekeeping` |
| GET | `/admin/stays/calendar` | auth + `can:gerer:nuitees` | `StayOperationsController@calendar` |
| GET | `/admin/users` | auth + `can:gerer:utilisateurs` | `AdminUserController@index` |
| PATCH | `/admin/users/{user}` | auth + `can:gerer:utilisateurs` | `AdminUserController@update` |
| POST | `/admin/users/{user}/request-document` | auth + `can:gerer:utilisateurs` | `AdminUserController@requestDocument` |
| PATCH | `/admin/validate/{type}/{id}` | auth + `can:consulter:dashboard-admin` | `ValidationQueueController@decide` |
| GET | `/admin/vehicles` | auth + `can:consulter:dashboard-admin` | `AdminCatalogController@vehicles` |

### Santé

| Méthode | URI | Accès | Contrôleur |
| --- | --- | --- | --- |
| GET | `/version` | public | `Closure` |
