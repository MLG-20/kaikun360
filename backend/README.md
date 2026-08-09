# Kaikun 360 — Backend (API Laravel)

> Plateforme tout-en-un de l'immobilier, du tourisme et des services au Sénégal :
> achat/vente & location, nuitées, gestion locative, construction, tourisme &
> expériences, mobilité, diaspora, team building, marketplace de prestataires —
> le tout servi par une **API REST modulaire** en Laravel.

API backend du projet **Kaikun 360**. Ce dépôt contient l'application serveur
(Laravel). Le frontend (Angular) fait l'objet d'un chantier séparé.

- **261 endpoints** REST versionnés (`/api/v1`) — voir [`API.md`](API.md)
- **12 modules** métier isolés (dont `Assistant`, hors CDC)
- **63 tables**, référentiel géographique du Sénégal inclus
- **999 tests** automatisés (3502 assertions), tous verts ✅

---

## À quoi sert ce dépôt (expliqué simplement)

Ce dépôt, c'est le **moteur** de Kaikun 360 — le « cerveau » de la plateforme.
Il n'a **aucun écran visible** : on ne le « regarde » pas, il travaille en
coulisses. Son rôle :

- **Ranger et conserver toutes les données** (comptes, biens, réservations,
  paiements, documents…) dans une base de données.
- **Faire respecter les règles du métier** — par exemple : « un bien doit être
  vérifié par un agent avant d'être publié », « seul le titulaire d'une
  réservation peut la payer », « un prestataire non validé ne peut pas publier ».
- **Protéger l'accès** : chaque personne ne voit que ce qu'elle a le droit de voir.

Pour communiquer avec le monde extérieur, le moteur expose une **API**. Une API,
c'est comme le **guichet standardisé** du moteur : le site web (et demain
l'application mobile) lui adressent des demandes normalisées (« connecte cet
utilisateur », « donne la liste des villas à Saly », « enregistre cette
réservation ») et reçoivent des réponses. Comme ce guichet est standard,
**plusieurs écrans différents peuvent utiliser le même moteur** sans le modifier.

### Comment le moteur est organisé

Le code est découpé en **12 modules** indépendants, qui correspondent aux univers
et aux espaces de la plateforme :

- **Core** = les comptes, la connexion, les rôles (la porte d'entrée) ;
- **Immo, Stay, Manage, Build, Explore, Mobility, Diaspora, TeamBuilding, Pro** =
  les 9 univers métier (voir [Domaines fonctionnels](#domaines-fonctionnels)) ;
- **Admin** = le back-office, les coulisses de l'équipe Kaikun ;
- **Assistant** = l'assistant conversationnel transverse (F10), **hors cahier des
  charges** : il n'a aucun privilège propre et passe par les mêmes autorisations
  que le reste.

Chaque module a son propre `README.md` qui explique sa logique. Chaque fichier de
code est **abondamment commenté en français**.

### Où en est le moteur ?

**Il est terminé** (tous les univers, la sécurité, les paiements, les
notifications) et **vérifié par 999 tests automatiques** — des petits programmes
qui rejouent les scénarios importants à chaque modification pour garantir que rien
ne casse. Détail en fin de document ([État d'avancement](#état-davancement)).

---

## Sommaire

- [Stack technique](#stack-technique)
- [Architecture modulaire](#architecture-modulaire)
- [Domaines fonctionnels](#domaines-fonctionnels)
- [Structure du projet](#structure-du-projet)
- [Conventions d'API](#conventions-dapi)
- [Sécurité, rôles & RGPD](#sécurité-rôles--rgpd)
- [E-mails transactionnels](#e-mails-transactionnels)
- [Performance](#performance)
- [Installation & démarrage](#installation--démarrage)
- [Configuration (.env)](#configuration-env)
- [Tests](#tests)
- [Documentation](#documentation)
- [État d'avancement](#état-davancement)

---

## Stack technique

| Composant | Choix |
| --- | --- |
| Langage | PHP **8.3+** (développé sous 8.4) |
| Framework | **Laravel 13** |
| Authentification | **Laravel Sanctum 4** (jetons Bearer) |
| Rôles & permissions | **spatie/laravel-permission 8** |
| Journalisation d'audit | **spatie/laravel-activitylog 5** |
| Traitement d'images | **intervention/image 4** (pilote GD) |
| Base de données | **MySQL 8** |
| Cache / sessions / files | **Redis 7** |
| Paiement | **PayTech** (abstraction + webhook signé HMAC) |
| Tests | **PHPUnit 12** |

---

## Architecture modulaire

Le code métier est découpé en **modules autonomes** sous `app/Modules/<Module>/`,
chacun avec ses propres `Models/`, `Http/` (Controllers, Requests, Resources),
`Services/`, `Policies/`, `Enums/`, `Events/` et `routes/`. Les routes de chaque
module sont chargées automatiquement par un glob depuis `routes/api.php`.

Les briques réellement transverses (Booking, Review, Media, Report polymorphes,
**Conversation/Message** [messagerie générique], enums de statut, `ApiResponse`,
`Settings`, `CatalogCache`) vivent dans `app/Models`, `app/Enums`, `app/Http` et
`app/Support`.

```
app/
├── Enums/            # Enums transverses (statuts booking/paiement/requête…)
├── Models/           # Modèles transverses (Booking, Review, Media, Conversation/Message, Favorite, géo…)
├── Http/             # Controllers & Resources transverses
├── Support/          # ApiResponse, Settings, CatalogCache, Billing/, Payments/…
└── Modules/
    ├── Core/         # Utilisateurs, auth, rôles, profils, KYC
    ├── Immo/         # Biens immobiliers (achat/vente/location mensuelle)
    ├── Stay/         # Nuitées (location courte durée)
    ├── Manage/       # Gestion locative (mandats, loyers, incidents, reversements)
    ├── Build/        # Construction / rénovation (estimation, jalons, rapports)
    ├── Explore/      # Tourisme & expériences
    ├── Mobility/     # Transport & mobilité (véhicules, services)
    ├── Diaspora/     # Projets diaspora (affectation d'agents)
    ├── TeamBuilding/ # Séminaires d'entreprise (devis composés)
    ├── Pro/          # Marketplace de prestataires (validation, missions, notation)
    ├── Admin/        # Back-office (dashboard, files de validation, supervision)
    └── Assistant/    # Assistant conversationnel transverse (F10) — hors CDC
```

Chaque module possède son propre `README.md` documentant sa logique métier.

---

## Domaines fonctionnels

- **Core** — inscription (5 profils : client, propriétaire, prestataire, diaspora,
  entreprise), connexion e-mail/téléphone **ou Google** (flux ID token),
  **double authentification e-mail (2FA) obligatoire pour les comptes admin/
  super_admin** avec jeton back-office à expiration courte (F7.1.d),
  vérification par code, récupération de compte, profils, documents KYC sur disque
  privé (téléchargement par URL signée), 8 rôles / permissions fines.
  **Photo de profil / logo d'entreprise (F8.0)** — `POST` / `DELETE
  /users/me/avatar`, colonne `profiles.avatar_path`. Une seule colonne pour les
  deux usages : `Profile::avatarKind()` renvoie `logo` pour un profil
  *entreprise*, `photo` sinon, et c'est cette valeur (exposée par
  `ProfileResource`) qui dit à l'interface quoi demander. ⚠️ **Disque PUBLIC**,
  contrairement au KYC : une image affichée en permanence ne peut pas dépendre
  d'une URL signée qui expirerait en pleine session. La contrepartie est une
  validation stricte — **image matricielle uniquement** (`image` + `mimes`, donc
  ni PDF ni SVG, ce dernier pouvant porter du script), 100×100 à 4000×4000 px,
  2 Mo max. Un remplacement **supprime l'ancien fichier**, et
  `AccountAnonymizer` **efface la photo** (fichier compris) : un visage servi
  publiquement après suppression du compte serait une fuite. Les deux routes
  renvoient l'utilisateur **complet**, pour que le front n'ait qu'une source de
  vérité à rafraîchir. Nécessite `php artisan storage:link` sur l'environnement.
- **Immo** — catalogue public filtrable, dépôt de biens, validation par un agent,
  documents, comparaison (les favoris sont devenus transversaux, tous univers).
- **Stay** — catalogue de nuitées, disponibilité, réservation anti-double-booking,
  caution ; check-in/out & ménage côté back-office.
- **Manage** — mandats de gestion, loyers, incidents, dépenses, reversements
  propriétaires, rapport mensuel. Le **pilotage** (créer un mandat, ajouter et
  encaisser un loyer, signaler et résoudre un incident, enregistrer une dépense,
  préparer et marquer un reversement) existe depuis B4.6 sous la permission
  `gerer:gestion-locative` ; il est branché sur la **fiche mandat du back-office**
  depuis F7.3.a — jusque-là aucune interface ne l'atteignait. À cette occasion,
  `GET /manage/mandates/{id}` charge enfin les **dépenses** (créables mais
  jusqu'alors jamais relisables) et `MandateResource` expose les **clauses**
  (`terms`) du mandat, soit les « contrats » de la ligne CDC §6.
- **Build** — simulateur de coût de construction, jalons de chantier, rapports
  photo/vidéo polymorphes, **devis ventilés par lot** (F7.3.e2) et leur **réponse
  par le client** (F3.9). Trois règles à ne pas défaire :
  1. **Les brouillons ne descendent jamais jusqu'au client.** `ConstructionQuoteController::index`
     et `ConstructionRequestController::mine` filtrent sur `status != brouillon`
     pour qui n'a pas `gerer:chantiers`. Un chiffrage en cours de composition,
     aux montants provisoires, n'est pas un document du client — le lui montrer
     puis le changer détruit la confiance que la plateforme vend.
  2. **L'envoi notifie le client** (`ConstructionQuoteSent` → `NotifyClientOfConstructionQuote`).
     Sans cela l'écran de réponse existe mais personne n'y va : le statut
     basculait en base, en silence. Réglage `QUOTE_RECEIVED` (partagé avec le
     devis transversal, pour qu'une coupure au back-office soit cohérente).
  3. **Répondre est réservé au CLIENT** (policy `respond`), plus étroit que
     `view` : accepter un devis est son engagement financier, ni l'agent ni
     l'admin ne le prennent à sa place.
- **Explore** — expériences touristiques, capacité, réservation, annulation/
  remboursement, et **supervision back-office** (F7.2.k — CDC §6) servie par le
  module Admin : `GET /admin/experiences` renvoie `AdminExperienceResource`
  (remplissage `seats_taken`/`seats_left` via `withSum`, prestataire, filtre
  `destination`) et `GET /admin/tourism/destinations` reconstruit la couverture
  par destination en `GROUP BY` (les destinations sont une **colonne**, pas une
  entité). ⚠️ Capacité = **total par circuit**, pas par session datée. Les
  **guides** et **restaurants** du cahier des charges ne sont pas modélisés ici :
  ce sont des catégories du module Pro + des drapeaux d'inclusion, sans lien
  guide ↔ circuit (écart assumé, documenté).
- **Mobility** — véhicules (avec contrôle de conformité assurance/pirogue),
  services de mobilité, caution. **F8.10** : `GET /mobility-services/{id}`
  (fiche publique d'un départ **avec son remplissage**) — l'endpoint n'existait
  pas, le module s'arrêtant à la recherche, ce qui interdisait toute page de
  trajet et donc toute réservation en ligne. ⚠️ **Anti double-location** ajouté
  à `POST /vehicles/{id}/bookings`, qui ne vérifiait aucun chevauchement : deux
  clients pouvaient repartir avec le même véhicule le même jour. Et
  **supervision back-office**
  (F7.2.j — CDC §6) servie par le module Admin : `GET /admin/vehicles` renvoie
  désormais `AdminVehicleResource` (sur-ensemble du format public : assurance,
  identité du chauffeur, gilets, drapeaux de conformité, prestataire ; filtre
  `driver=1|0`) et `GET /admin/mobility-services` expose les départs tous
  statuts avec leur **remplissage** (`seats_taken`/`seats_left` agrégés en une
  requête via `withSum`, annulations dérivées de `BookingStatus::estAnnulee()`).
  Les champs de contrôle restent **hors** du catalogue public.
- **Diaspora** — projets pilotés par un agent (affectation auto au moins chargé),
  rapports d'avancement, et **pilotage back-office** (F7.2.i — CDC §6) : file
  priorisée filtrable + `PATCH /diaspora-projects/{id}` (statut et/ou priorité,
  sans effet de bord — permet de clôturer/annuler et de (re)prioriser hors
  affectation), resource enrichie `client`+`agent`.
- **TeamBuilding** — demandes d'entreprise, devis composés multi-prestataires
  avec marge, et **affectation des prestataires** (F7.2.h — CDC §6) : une
  affectation crée une **mission Pro** rattachée à la demande
  (`provider_missions.team_building_request_id` + `category`, migration additive),
  prestataire validé requis, commission figée, cycle de mission standard —
  endpoints `GET|POST /team-building-requests/{id}/assignments` (policy `manage`).
- **Pro** — inscription prestataire, édition de son dossier (descriptif +
  certifications), disponibilités, charte qualité, validation, missions &
  commission, **avis reçus** (avis sur ses ressources + avis directs après mission,
  `GET /providers/reviews`) et notation agrégée à partir de ces avis.
  **Justificatif de certification (F8.0)** — `POST /providers/certifications`
  accepte désormais un **fichier** (multipart, champ `file`, PDF/JPG/PNG ≤ 5 Mo),
  stocké sur le **disque privé** et relu par URL **signée** 10 minutes
  (`GET /providers/certifications/{id}/download`, route hors `auth:sanctum` — la
  signature fait foi, comme pour le KYC). Solde une dette de B6 : `file_path`
  existait sans qu'aucun contrôleur n'accepte de fichier, donc la colonne
  fichier du back-office était structurellement vide. Colonnes ajoutées :
  `disk`, `original_name`, `mime_type`, `size` (alignement sur `user_documents`).
  Supprimer une certification **supprime le scan**. ⚠️ La pièce est **facultative**
  (déclarer maintenant, scanner plus tard) : `has_file` distingue « pas de pièce »
  de « pièce à contrôler». ⚠️ `download_url` n'est exposée que sur des réponses
  authentifiées — les certifications ne sont chargées que sur `/providers/mine`,
  l'inscription et l'admin, **jamais dans le catalogue public**.
- **Admin** — tableau de bord KPI, file de validation générique, gestion des
  comptes, paramétrage (commissions/tarifs/FAQ/pages), export comptable JSON/CSV,
  supervision des paiements (remboursements + confirmation manuelle Wave/OM).
  **Poste de commandement de l'équipe (F7.1)** : annuaire + enrôlement des
  employés (`/admin/team`), **délégation des dossiers par personne** (« grant
  pur » : le rôle agent n'ouvre que l'accès, chaque permission se délègue
  individuellement — `/admin/team/{id}/permissions`, catalogue `AdminPermission`)
  et **pointeuse** (présences entrée/sortie + feuille mensuelle + export CSV,
  `/admin/attendance`). **Supervision des dossiers de suivi** (`AdminDossierController`,
  B13.7.2) : listes transverses en lecture seule des demandes de **construction**
  (`GET /admin/construction-requests`) et des **mandats** de gestion locative
  (`GET /admin/mandates`), avec compteurs d'avancement (jalons/rapports côté
  construction ; loyers/incidents/dépenses/reversements côté mandat) — exploitées
  par l'écran back-office **Dossiers** (F7.2.e).
  **File de traitement des demandes (F8.9)** (`AdminRequestController`,
  `traiter:demandes`) : `GET /admin/requests` (toutes les demandes clients,
  urgences d'abord puis les plus anciennes, filtres service/statut/priorité +
  recherche par référence, ville ou identité du demandeur),
  `GET /admin/requests/{id}` (fiche : demandeur joignable, devis, historique) et
  `GET /admin/requests/filters` (référentiels lus dans les enums). ⚠️ **Comble un
  trou** : l'alerte interne « Nouvelle demande à traiter » existait depuis B11.2
  et le statut se pilotait déjà, mais **aucune route ne listait les demandes** —
  l'équipe recevait l'e-mail d'un dossier introuvable. Le pilotage reste sur
  `PATCH /requests/{id}/status` (même permission) : la machine à états n'est pas
  dupliquée, l'API expose `allowed_transitions` pour l'écran.
  **Le devis va jusqu'au paiement (F8.11)** — `App\Services\QuoteConversionService`.
  ⚠️ **`PATCH /quotes/{id}` ne faisait que changer une colonne** : accepter un
  devis ne créait aucune réservation, donc aucun montant exigible, et
  `POST /payments/initiate` réclamant un `booking_id`, le client ne pouvait pas
  régler ce qu'il venait d'accepter. L'acceptation crée désormais un `Booking`
  dont la **cible polymorphe est le devis lui-même** (`bookable_type = Quote` —
  aucune migration, `bookings` est polymorphe depuis B3.3 ; le sur-mesure n'a
  aucune fiche au catalogue à désigner). Conversion **idempotente** (verrou de
  ligne + `morphOne`), commission figée au passage comme partout depuis F8.4, et
  **aucune notification depuis le service** — il tourne en transaction, un e-mail
  parti avant un `rollback` annoncerait une réservation inexistante. Nouvelle
  colonne **`quotes.agent_id`** : l'auteur du chiffrage devient l'interlocuteur
  nommé du client, et `QuoteAnsweredNotification` ne part qu'à **lui seul**
  (événement back-office `quote_answered`). ⚠️ `POST /requests/{id}/quotes`
  existait depuis B11.3 **sans aucun appelant** : tous les devis venaient du
  seeder.
  **Les DEUX autres familles de devis vont aussi jusqu'au paiement (F8.14)** —
  `QuoteConversionService::convertTeamBuilding()` et `::convertConstruction()`.
  ⚠️ **Le même trou existait en trois exemplaires** : F8.11 n'avait comblé que les
  devis *génériques* (`quotes`), or le produit en a **trois familles**, chacune
  avec sa table et son contrôleur. `TeamBuildingQuoteController::accept()` et
  `ConstructionQuoteController::accept()` ne faisaient eux aussi que changer deux
  colonnes `status` — le premier déclenchait même un écouteur qui écrivait « le
  suivi opérationnel s'appuiera sur la couche Bookings/Quotes », promesse jamais
  tenue. Une **entreprise** pouvait accepter un séminaire et un **client** un
  chantier à plusieurs millions sans que rien ne devienne exigible. Les deux
  acceptations créent désormais un `Booking` dont la cible polymorphe est le devis
  lui-même, renvoient `{quote, booking}`, et notifient le destinataire **après** la
  transaction (`TeamBuildingQuoteAcceptedNotification`,
  `ConstructionQuoteAcceptedNotification`, événement `team_building_quote_accepted`).
  ⚠️ **La commission n'est PAS celle de `CommissionCalculator`** ici, mais la
  **marge déjà chiffrée dans le devis** (`margin_xof`) : ces deux devis sont
  composés coûts + marge, et c'est le total qui est signé — appliquer le taux
  commun par-dessus facturerait deux fois la même rémunération. Les deux
  ressources exposent `booking` (chargé avec les devis), sans quoi le montant
  exigible redeviendrait invisible au premier rechargement.
  ⚠️ **`SpaceLink` retombe désormais sur le RÔLE** quand le profil manque : son
  repli `/mon-espace` semblait inoffensif (« l'espace client existe toujours »)
  mais cette adresse est **gardée par le rôle `client`** côté Angular — une
  entreprise l'aurait reçue comme un refus d'accès, dans l'e-mail même qui devait
  l'emmener payer.
    **Boîte de réception du support (F8.12)** (`AdminConversationController`,
  `repondre:messages`) : `GET /admin/conversations` (portées `mine` par défaut /
  `unassigned` / `all`, archive `closed=1`, recherche sur le sujet et
  l'interlocuteur), `GET /admin/conversations/{id}` (échange complet + vivier
  d'agents), `POST /admin/conversations/{id}/messages` (répondre) et
  `PATCH /admin/conversations/{id}` (réassigner / clore). ⚠️ **Sans cet écran, la
  messagerie n'existait pas** : le socle F3.7 savait lire et répondre, mais
  **aucun geste n'ouvrait un fil** et personne côté équipe ne les voyait. Côté
  client, `POST /messages/support` (`MessageController@startWithSupport`)
  n'accepte **aucun destinataire** — `SupportAssignmentService` désigne l'agent
  du vivier le moins chargé ; `POST /messages` (destinataire désigné) devient
  réservé à l'équipe, laisser un client écrire directement à un propriétaire
  contredisait l'architecture « support pivot ». ⚠️ **`repondre:messages` sert
  aussi de vivier** : la déléguer, c'est mettre quelqu'un de permanence ; vivier
  vide → fil **non assigné** (jamais perdu). Deux colonnes neuves sur
  `conversations` (`assigned_agent_id`, `closed_at`), et
  `App\Support\Messaging\ConversationContext` (liste blanche de 8 slugs) branche
  enfin `context_type`/`context_id`, restés vides depuis F3.7 — un dossier
  personnel n'est rattaché que s'il appartient à l'auteur, sinon il est ignoré.
  **Relève périodique (F8.12.a)** : `GET /messages/{id}` et
  `GET /admin/conversations/{id}` acceptent **`?after=<message_id>`** et ne
  renvoient alors que les messages plus récents — un fil ouvert se tient à jour
  sans retélécharger l'historique. ⚠️ **Relève à vide = aucune écriture** :
  `last_read_at` n'est touché que s'il y a réellement du nouveau, sinon chaque
  battement produirait un `UPDATE` pour rien.
  **Qui reçoit un nouveau fil (F8.12.b)** — `SupportAssignmentService` : (1) le
  vivier = porteurs de `repondre:messages`, **désormais portée par le rôle**
  `agent_kaikun` (tout agent est de permanence d'office ; elle sort de
  `AdminPermission::delegable()` via `carriedByRole()`) ; (2) **en poste
  d'abord** — une session de pointeuse ouverte (`Attendance::open()`) ; (3) parmi
  eux, **le moins chargé** (fils ouverts assignés). ⚠️ **Deux replis** : personne
  en poste → tout le vivier (un message ne dépend jamais d'un pointage oublié) ;
  vivier vide → fil **non assigné**. Le super administrateur réassigne à la main
  (`PATCH /admin/conversations/{id}`).
  **Un tiers dans le fil (F8.12.c)** : `GET …/candidates` (personne du dossier
  via `ConversationContext::holder()`, puis recherche limitée aux rôles
  propriétaire/prestataire), `POST …/participants` (il voit **tout
  l'historique** et est notifié), `DELETE …/participants/{user}` (ni le
  demandeur ni l'agent responsable — 422 ; les messages écrits restent).
  ⚠️ **`ContactMasker`** masque e-mails et suites d'au moins **7 chiffres** dans
  `MessageResource` **pour les lecteurs non-staff seulement** : l'équipe doit
  voir le texte entier pour arbitrer un litige, et 7 chiffres (pas 6) pour ne
  pas hacher un prix. Le filtre réduit la friction, **il ne verrouille rien**.
  **Gestion documentaire transverse** (`AdminDocumentController`) — les **six**
  familles de la ligne CDC §6 « Documents » depuis F7.4.c : pièces d'identité
  (KYC), documents de biens, certifications prestataires, preuves de reversement,
  **mandats/contrats** et **rapports de suivi** (le modèle `Report` étant
  polymorphe, la même liste couvre chantiers et dossiers diaspora). ⚠️ Un mandat
  porte ses clauses en **texte** (`management_mandates.terms`) : il n'y a pas de
  contrat scanné téléversé, la ligne renvoie vers la fiche du mandat.
  **Revue des médias avant publication (F8.1)** — la file de validation ne
  portait que le libellé et le déposant : un agent publiait une annonce sur le
  site vitrine **sans avoir vu une seule photo**. Chaque entrée de file porte
  désormais `media` (`Validation\MediaEntry`, pendant d'`OwnerEntry`) : compteurs
  + aperçu de 4 vignettes, avec eager-loading d'`allMedia` dans `pendingQuery()`.
  Deux routes s'ajoutent : `GET /admin/queue/{type}/{id}` (dossier complet —
  galerie entière, masqués compris, et `fields` propres au type via `toDetail()`,
  consultable **même après décision** pour revoir son geste) et
  `PATCH /admin/media/{media}/status` (masquer/réafficher **une** photo au lieu
  de refuser toute l'annonce). Le média masqué n'est pas supprimé : il quitte
  `media()` sans quitter `allMedia()`. Autorisation de la modération : la
  permission de validation du **type parent** (`valider:bien`…), pas un droit
  générique — qui publie une ressource arbitre ce qu'elle montre. Les listes de
  supervision exposent en plus `media_count` / `media_hidden_count` (`withCount`)
  pour repérer une annonce publiée sans visuel.
  **Les fiches de dossier (F8.2)** — cinq écrans n'étaient que des listes :
  Nuitées, Mobilité, Tourisme, Paiements, Avis & qualité. Sept points d'accès de
  détail les complètent (`GET /admin/stay-bookings/{id}`, `/admin/vehicles/{id}`,
  `/admin/mobility-services/{id}`, `/admin/experiences/{id}`,
  `/admin/providers/{id}`, `/admin/payments/{id}`, `/admin/reviews/{id}`),
  chacun gardé par **la permission de sa liste** et en **lecture seule** — les
  gestes restent aux routes d'action, qui portent les règles et la trace.
  Deux méritent l'attention. **Le paiement** transporte ce que `PaymentResource`
  n'expose pas et ne doit pas exposer (elle sert aussi l'espace client) :
  `provider_reference`, `signature_verified`, la preuve Wave/OM et le montant
  déjà remboursé — construits dans le contrôleur, derrière `gerer:paiements`. Il
  renvoie en plus `can_confirm` / `can_refund` : **le serveur dit ce qu'il
  accepterait**, au lieu de laisser le frontend redéclarer les règles du module
  de paiement et en diverger. ⚠️ `gerer:paiements` est une permission de
  **gouvernance** (CDC §7, « accès financier limité ») : un agent de terrain ne
  l'a pas, même avec `AdminPermission::operational()`. **L'avis** porte son
  `context` — les autres avis *publiés* de la ressource notée, leur moyenne, le
  nombre de plaintes : une plainte isolée est un texte à modérer, la troisième du
  mois est un problème de prestataire. `MediaEntry` et `OwnerEntry` (F8.1) sont
  réutilisés par les fiches véhicule, circuit et séjour.
  ⚠️ **Une réservation dont la ressource a disparu reste consultable** (`stay:
  null`, « Ressource retirée ») plutôt que 404 : le séjour a eu lieu, le règlement
  a été encaissé, et un dossier financier qui s'évanouit avec son bien est
  ingérable en cas de litige. 18 tests.
  ⚠️ **Dette B12 soldée ici** : seul `Property` avait une relation `media()`.
  `Vehicle` (commentaire « sera branchée en B12 ») et `TourismExperience`
  acceptaient déjà des dépôts via `POST media/upload` qu'aucune relation ne
  relisait. Les trois utilisent le trait `App\Models\Concerns\HasMedia`.
  **Permissions exposées au frontend (F7.4.a)** : `UserResource::withPermissions()`
  joint les permissions back-office effectives. C'est un **opt-in explicite**,
  posé sur les seules réponses qui représentent le compte connecté (connexion,
  inscription, Google, 2FA, vérification, `/users/me`, mise à jour de profil) et
  sur aucune liste — la ressource sert aussi aux annuaires admin (une requête de
  permissions par ligne = N+1) et les droits d'un collègue n'ont pas à circuler.
  ⚠️ **Ne PAS revenir à une déduction du type `$request->user()->id === $this->id`**
  (F7.4.e) : sur `/auth/login` et `/auth/two-factor` la requête n'est pas encore
  authentifiée, `$request->user()` y vaut `null`, et le compte recevait un jeton
  sans ses permissions — donc un rail amputé jusqu'au rechargement suivant. Le **super_admin** est traité à part (`User::permissionsBackOffice()`) :
  ses droits venant du `Gate::before`, il n'a aucune permission assignée et se
  serait retrouvé avec le rail le plus vide. Sert au cloisonnement du rail côté
  Angular ; les `can:` des routes `/admin/…` restent la sécurité réelle.
  **Paramètres & contenu (F7.2.l — CDC §6, dernier des 14 modules)** :
  - **Référentiel géographique éditable** (`AdminGeoController`) — les « villes »
    du cahier des charges. Ce référentiel (14 régions, 46 départements, ~557
    communes) était **figé** depuis ses seeders et exposé en lecture seule par
    `GeoController`. Il devient maintenable : `GET /admin/geography`
    (arborescence + compteurs), `GET|POST /admin/communes`,
    `PATCH|DELETE /admin/communes/{id}`, et les mêmes verbes pour
    `/admin/departments`. Deux garde-fous, car ces données sont référencées
    ailleurs : les **régions restent en lecture seule** (nomenclature nationale),
    et **aucune suppression en cascade silencieuse** — `properties.commune_id` /
    `users.commune_id` sont en `nullOnDelete`, `communes.department_id` en
    `cascadeOnDelete` : supprimer un élément encore rattaché renvoie **409** avec
    le nombre d'objets qui le retiennent, au lieu d'effacer des localisations
    (ou des dizaines de communes) sans le moindre signal.
  - **Pilotage des notifications** (`App\Support\Notifications\NotificationSettings`
    + enum `NotificationEvent`) — réglages `notifications.email_enabled`,
    `notifications.sms_enabled`, `notifications.events`. Les canaux étaient codés
    en dur dans chaque `via()` : impossible de couper le SMS (facturé à l'envoi)
    ou de calmer un événement bavard sans redéployer. Les 12 notifications
    d'exploitation passent désormais par un **point de décision unique** :
    événement coupé → `via()` vide (Laravel n'envoie rien, pas même la trace en
    base), canal coupé → canal retiré, SMS sans numéro → retiré (règle qui vivait
    dupliquée). Le canal `database` n'est jamais coupé par les canaux : il est
    gratuit et constitue la trace. ⚠️ **Les notifications de sécurité (codes de
    vérification, 2FA) sont volontairement HORS de ce pilotage** — un réglage
    capable de condamner l'accès n'a pas sa place dans une interface d'admin.
  - **Réseaux sociaux** : réglages `social.facebook|instagram|tiktok|linkedin|youtube`
    (groupe `general`), exposés au public par `GET /contact-info` sous la clé
    `social` — **les réseaux vides sont omis**, le pied de page n'affiche donc
    jamais de lien mort. `PATCH /admin/settings` refuse (**422**) une valeur non
    vide qui n'est pas une URL `http(s)` complète : ces liens sont publics, une
    faute de saisie y resterait visible et cliquable.
  - **Catégories** : ⚠️ écart CDC assumé. Ce sont des **enums PHP** qui portent la
    logique métier (règles de validation, calcul de commission, filtres) ;
    `GET /admin/reference` les expose en lecture seule et l'écran l'explique.

Couches **transversales** : demandes de service (machine à états stricte), devis
génériques, réservations polymorphes, **messagerie générique** (conversations à
participants + messages, réutilisable par tous les espaces), **favoris polymorphes**
(tous univers : bien, nuitée, véhicule, expérience, mobilité — voir
`App\Support\Favoritables`), médias (compression d'images), avis (réservés au
consommateur ayant consommé), notifications (e-mail/SMS asynchrones, canal
`database` in-app, WhatsApp click-to-chat), paiement (PayTech ou manuel Wave/Orange
Money).

---

### Le client peut enfin payer (F8.6)

⚠️ **Le cycle de paiement était complet des deux côtés, sauf en son milieu.** Le
backend savait initier un règlement depuis B14, le back-office savait les
superviser depuis F7.2, le PSP a été réaligné en F8.5 — mais **aucun écran du
site n'appelait `POST /payments/initiate`**. Un client pouvait réserver une
nuitée et n'avait ensuite aucun moyen de payer.

`BookingResource` expose désormais l'état de règlement, sans quoi aucun écran ne
pouvait le demander :

| Champ | Sens |
|---|---|
| `paid_xof` | total **encaissé** (paiements `complete` seulement) |
| `remaining_xof` | reste dû |
| `is_paid` | la réservation est soldée |
| `payable` | ni annulée, ni soldée → un règlement est possible |

⚠️ **`payments` doit être en eager loading** partout où l'on sérialise une liste
de réservations : `montantPaye()` interroge la relation, donc 15 réservations
sans ce chargement = 15 requêtes de plus. `BookingController::my()` et `show()`
le font.

⚠️ **Un paiement n'est jamais confirmé par le retour du client.** Les pages
`/paiement/succes` et `/paiement/annule` ne sont que des atterrissages : seul
l'IPN signé, reçu de serveur à serveur, fait passer la réservation à
« confirmée ». Elles n'affichent donc aucune donnée de réservation et ne
déclenchent rien — n'importe qui peut en taper l'adresse.

### Commission plateforme — un seul taux, fixé par la direction (F8.4)

La commission de Kaikun n'est **jamais codée en dur**. Elle vient du back-office
(*Paramètres → Réglages → Commissions*) et s'applique sans redéploiement :

| Réglage | Repli livré | Portée |
|---|---|---|
| `commission.default_rate` | 12 % | Réservations **nuitées, tourisme, mobilité** (véhicule + service) et **toute mission prestataire** (chantier, team building, Pro) |
| `teambuilding.margin_rate` | 15 % | Marge des devis de pack team building |
| `build.margin_rate` | 15 % | Marge des devis de chantier |

Les valeurs de la colonne « repli » sont des **ordres de grandeur livrés**, pas
des décisions : elles s'appliquent tant que la direction n'a rien saisi.

**La gestion locative fait exception, volontairement** : son taux est saisi
**mandat par mandat** à la création du contrat (`management_mandates.commission_rate`,
obligatoire, 0–100 %) — une commission de gestion se négocie bien par bien, pas
globalement. C'est ce taux qui alimente le rapport mensuel du mandat.

Le calcul est centralisé dans
[`app/Support/Billing/CommissionCalculator.php`](app/Support/Billing/CommissionCalculator.php).
⚠️ **Il vivait dans `Modules/Mobility/Services/`** (son premier appelant, B7.4) :
déplacé en F8.4, sept modules en dépendant désormais.

⚠️ **La commission est FIGÉE à la réservation**, jamais recalculée : changer le
taux ne réécrit pas l'historique comptable des réservations déjà prises.

⚠️ **Nuitées et tourisme ne la calculaient pas avant F8.4.** `commission_xof`
restait à `0` sur ces deux univers — l'export comptable et le tableau de bord
sous-estimaient donc le revenu réel. La caution n'entre pas dans l'assiette :
c'est un dépôt rendu au client, pas un revenu.

## Structure du projet

```
backend/
├── app/                 # Code applicatif (voir Architecture modulaire)
├── bootstrap/           # Amorçage, mapping des exceptions API
├── config/              # Configuration (services, cache, cors…)
├── database/
│   ├── factories/       # Factories de test
│   ├── migrations/      # 52 migrations (55 tables)
│   ├── schema/          # Dump de schéma MySQL (accélère les tests)
│   └── seeders/         # Rôles/permissions, référentiel géographique
├── resources/views/
│   └── emails/          # Gabarit unique des e-mails (HTML + texte brut)
├── routes/              # api.php (glob des modules) + transversal.php
├── tests/               # Feature/<Module> (PHPUnit)
├── API.md               # Référence des 261 endpoints
├── PERFORMANCE.md       # Durcissement & performance
└── CONFIDENTIALITE.md   # RGPD & rétention des données
```

---

## Conventions d'API

- **Préfixe** : toutes les routes sont sous `/api/v1`.
- **Format** : JSON (`Accept: application/json`).
- **Authentification** : jeton Bearer Sanctum (`Authorization: Bearer <token>`).
- **Enveloppe de succès** : `{ "data": … }` ou enveloppe paginée native
  (`{ data, links, meta }`).
- **Erreurs** : `{ "message": …, "errors": { champ: [messages] } }`. Codes
  `401 / 403 / 404 / 422 / 429 / 502`.
- **Limitation de débit** : 60 req/min par défaut ; renforcée sur l'auth
  (10/min/IP) et le paiement (15/min/utilisateur).
- **Permissions** : `super_admin` contourne tout (`Gate::before`) ; sinon
  permissions fines Spatie (`can:*`).

Détail complet : [`app/Support/README.md`](app/Support/README.md) et
[`API.md`](API.md).

---

## Sécurité, rôles & RGPD

- **8 rôles** (visiteur → super_admin) et **permissions fines** (Spatie),
  matrice de rôles verrouillée par des tests.
- **Garde « compte vérifié »** sur les actions sensibles (réservation, paiement,
  publication).
- **Audit** (Activitylog) sur les modifications de prix, validations de paiement,
  suppressions.
- **Documents privés** (KYC, documents de biens) sur disque `local`, accès par
  **URL signée temporaire**.
- **Webhook PayTech** vérifié par **signature HMAC-SHA256** (avec réconciliation
  de montant).
- **RGPD** : anonymisation du compte sur demande (`DELETE /users/me`) — voir
  [`CONFIDENTIALITE.md`](CONFIDENTIALITE.md).

---

## E-mails transactionnels

L'e-mail est le **seul canal de communication réellement maîtrisé** de la
plateforme. Kaikun 360 vendant de la *confiance* sur un marché où la méfiance est
la règle, ces messages sont traités comme un élément de produit à part entière —
pas comme une notification technique.

**22 e-mails**, tous issus d'un gabarit unique de marque (charte du site :
bleu `#0348FB`, navy `#03193F`, or `#D3AE52`, crème `#F7F4EB`).

| Point | Mise en œuvre |
| --- | --- |
| Un seul gabarit | `resources/views/emails/branded.blade.php` — aucun autre HTML d'e-mail dans le projet |
| HTML **et** texte brut | Générés des mêmes données. Le HTML seul est un signal de spam, et certains clients n'affichent que le texte |
| Accueil personnalisé | `WelcomeNotification` — un message distinct par profil (client, diaspora, propriétaire, prestataire, entreprise), envoyé **à l'activation** du compte |
| Liens toujours valides | `SpaceLink` résout l'espace privé du destinataire (4 espaces distincts côté Angular) |
| Compatibilité | Tables, styles inline, aucune image distante, bouton « à toute épreuve » Outlook, responsive + mode sombre |
| Relecture | `http://127.0.0.1:8000/apercu-emails` — les 22 e-mails dans le navigateur, données fictives, **aucun envoi** (local uniquement) |
| Relecture en conditions réelles | `php artisan mail:apercu <adresse>` — envoie les 22 e-mails (données fictives) dans une vraie boîte de réception |

Détail complet, règles de rédaction et ajout d'un e-mail :
[`app/Support/Mail/README.md`](app/Support/Mail/README.md).

---

## Performance

- **Index** de base de données sur les colonnes de filtrage/tri des catalogues.
- **Cache Redis** des résultats de catalogue/recherche, avec invalidation
  automatique (versioning) sur écriture des modèles.
- **Eager loading** systématique (chasse aux N+1, garde-fous testés).
- **Benchmark** local reproductible : `php artisan catalog:benchmark`.

Détail : [`PERFORMANCE.md`](PERFORMANCE.md).

---

## Plan du site — `GET /sitemap.xml` (F9.2)

Le backend publie le **plan du site** que lisent les moteurs de recherche :
toutes les pages publiques du site, plus **chaque fiche publiée** (biens,
nuitées, circuits, véhicules, départs à venir, pages éditoriales). Construction
dans [`app/Support/Seo/SitemapBuilder.php`](app/Support/Seo/SitemapBuilder.php),
route dans [`routes/web.php`](routes/web.php), tests dans
`tests/Feature/Transversal/SitemapTest.php`.

> ⚠️ **Route `web`, pas `api`** : un robot demande ce document tel quel, sans
> préfixe `/api/v1` ni en-tête `Accept`. Le mettre derrière l'API le rendrait
> introuvable.

> ⚠️ **Les URL produites sont celles du SITE** (`FRONTEND_URL`), jamais de l'API
> — le même réglage que les liens des e-mails depuis F8.8. Avec `APP_URL`, on
> publierait dans Google des liens qui répondent du JSON à des visiteurs.

> ⚠️ **Ce document doit sortir sous le domaine du SITE** : un moteur refuse un
> plan qui liste des URL d'un autre domaine que celui qui le sert. Le montage par
> défaut est le **relais du serveur de rendu Angular** (`frontend/src/server.ts`,
> variable `API_ORIGIN`), qui fonctionne sans configuration d'infrastructure. Une
> règle de reverse-proxy acheminant `/sitemap.xml` vers Laravel convient aussi.

> ⚠️ **Un plan du site n'est pas un contrôle d'accès** : tout ce qui y figure est
> appelé à être visité par des robots. Il applique donc **exactement les mêmes
> filtres que les endpoints publics** (`published()`, `bookable()`, et `aVenir()`
> pour la mobilité). Le jour où un filtre public change, celui-ci doit changer
> avec — sinon le plan annonce des pages qui répondent 404, ou pire, **des
> annonces qu'aucun agent n'a validées**.

Mis en cache une heure (six tables interrogées, et les robots repassent souvent).

---

## Installation & démarrage

### Prérequis

- PHP **8.3+** (extensions courantes Laravel + GD)
- Composer 2
- MySQL 8
- Redis 7

### Étapes

```bash
# 1. Dépendances
composer install

# 2. Environnement
cp .env.example .env
php artisan key:generate
# → renseigner DB_*, REDIS_*, PAYTECH_* dans .env

# 3. Base de données
php artisan migrate --seed        # tables + rôles/permissions + référentiel géo
php artisan db:seed --class=CommunesSeeder   # (optionnel) communes officielles ANSD
php artisan db:seed --class=DemoSeeder       # (optionnel, dev) annonces + demandes + réservations + gestion locative + profil & missions prestataire de démonstration
                                             # → remplit les 5 catalogues publics et les espaces connectés ; idempotent
                                             # ⚠️ F8.23 : les comptes de démo reçoivent enfin un
                                             #   PROFIL vérifié. Sans lui, aucun prestataire de
                                             #   démonstration ne pouvait déposer la moindre offre
                                             #   (403 muet) — les policies lisent
                                             #   `profiles.verification_status`, que le seeder ne
                                             #   renseignait pas. À REJOUER sur une base de démo
                                             #   existante.
php artisan db:seed --class=ContentSeeder    # (optionnel, dev) contenu éditorial de démo
                                             # → FAQ publiée ; appelle PublicPagesSeeder ; idempotent
php artisan db:seed --class=PublicPagesSeeder # ⚠️ PAS optionnel, PAS de la démo : les pages
                                             # légales du CDC §4.2 (CGU, CGV, confidentialité,
                                             # cookies, conditions de mandat, annulation/
                                             # remboursement) + mentions légales et À propos.
                                             # → À REJOUER APRÈS CHAQUE DÉPLOIEMENT qui ajoute
                                             #   une page : la garde est par SLUG, les pages
                                             #   déjà en base ne sont jamais réécrites (le texte
                                             #   validé par le juriste et saisi au back-office
                                             #   doit survivre à une relance).

# 4. Stockage privé (documents/médias)
php artisan storage:link

# 5. Lancer l'API
php artisan serve                 # http://127.0.0.1:8000
```

> Un **worker de queue** est nécessaire en production pour les notifications
> asynchrones (`php artisan queue:work`), supervisé (Supervisor/systemd).

> ⚠️ Un **cron** est nécessaire en production, au même titre que le worker :
>
> ```
> * * * * * cd /chemin/du/projet && php artisan schedule:run >> /dev/null 2>&1
> ```
>
> Sans lui, `reservations:cloturer` ne tourne jamais : **aucune réservation
> n'atteint le statut `terminee`**, et comme la `ReviewPolicy` l'exige, plus
> personne ne peut déposer d'avis (F8.15.a). Le symptôme est silencieux — le
> bouton « Donner mon avis » n'apparaît simplement jamais.

### Tâches planifiées et commandes de maintenance

| Commande | Rôle |
|---|---|
| `reservations:cloturer` | **Planifiée (3 h)** — fait avancer les réservations *datées* : `en_cours` quand le service commence, `terminee` quand il s'achève. N'avance ni les annulées ni les impayées (une réservation jamais payée n'est pas un service consommé). Rejouable sans risque ; `--dry-run` pour simuler. |
| `devis:rattraper-reservations` | Ponctuelle — crée les réservations manquantes des devis déjà acceptés avant F8.14. Idempotente, `--dry-run`, **sans notification**. |
| `mail:apercu <adresse>` | Ponctuelle — envoie les 22 e-mails transactionnels dans une vraie boîte. |

---

## Configuration (.env)

Clés principales (voir `.env.example` pour la liste exhaustive) :

| Clé | Rôle |
| --- | --- |
| `DB_*` | Connexion MySQL |
| `REDIS_*` | Cache, sessions, files d'attente |
| `CACHE_STORE=redis` | Cache des catalogues |
| `QUEUE_CONNECTION=redis` | Notifications asynchrones |
| `SMS_PROVIDER` | Canal SMS (`log` par défaut, `twilio` ou `orange` en prod) |
| `SMS_VERIFICATION_VIA_MAIL` | Envoyer par **e-mail** les codes destinés au téléphone. Actif d'office tant que `SMS_PROVIDER=log` (le SMS n'irait nulle part et l'utilisateur resterait bloqué), levé seul dès qu'un fournisseur réel est configuré. À ne renseigner que pour forcer un comportement. |
| `FRONTEND_URL` | **URL publique du site Angular** — sert à construire les liens des e-mails. À ne pas confondre avec `APP_URL`, qui désigne l'API. |
| `BRAND_SUPPORT_EMAIL` / `BRAND_SUPPORT_PHONE` / `BRAND_ADDRESS` | Coordonnées affichées en pied des e-mails (délivrabilité + confiance) |
| `PAYTECH_BASE_URL` / `PAYTECH_API_KEY` / `PAYTECH_API_SECRET` / `PAYTECH_ENV` / `PAYTECH_IPN_URL` | Paiement PayTech — `env` = `test` \| `prod`, l'IPN doit être **publique et HTTPS** (tunnel ngrok en local). ⚠️ Pas de « signing key » : l'`API_SECRET` signe aussi les notifications. |
| `CORS_ALLOWED_ORIGINS` | Origines autorisées (front Angular) |
| `ASSISTANT_ENABLED` | Interrupteur de l'assistant (F10). À `false`, l'endpoint répond 503 — permet de couper le service sans déploiement. |
| `ASSISTANT_DRIVER` | Cerveau de l'assistant : `rules` (déterministe, sans clé ni coût) ou `claude` (F10.4). Toute valeur inconnue retombe sur `rules`. |
| `ASSISTANT_RATE_PER_MINUTE` | Plafond du limiteur `assistant` (12/min par défaut) — parade au « déni de portefeuille ». |

> **Aucun secret n'est versionné** : `.env` est ignoré par git ; seuls les
> `.env.example` (valeurs factices) sont suivis.

---

## Tests

Suite **PHPUnit** (pas Pest), base dédiée `kaikun360_test`. Les tests chargent un
**dump de schéma** (`database/schema/mysql-schema.sql`) pour accélérer le démarrage.

```bash
php artisan test
# 909 tests, 3151 assertions — verts
```

> Après toute nouvelle migration : régénérer le dump
> (`php artisan schema:dump`) pour garder les tests rapides.

### Tests de parcours — la catégorie à ne pas confondre avec les autres

Six fichiers portent le nom `…JourneyTest` et obéissent à une **règle propre** :
ils n'écrivent **aucun état à la main**. Là où un test de couche pose ce dont il a
besoin (`'status' => 'terminee'`) et vérifie que la couche suivante sait le traiter,
un test de parcours exige que chaque état soit **produit par le produit lui-même** —
seule façon de prouver qu'il est atteignable en vrai.

| Fichier | Ce qu'il parcourt |
| --- | --- |
| [`Transversal/MoneyJourneyTest.php`](tests/Feature/Transversal/MoneyJourneyTest.php) | **Le circuit de l'argent** : visiteur → réservation → paiement → IPN PayTech → confirmation → clôture → dette → délai → virement au partenaire |
| [`Transversal/OfferLifecycleJourneyTest.php`](tests/Feature/Transversal/OfferLifecycleJourneyTest.php) | **La vie d'une offre** : déposée → corrigée → illustrée après coup → retirée (ou supprimée si elle n'a jamais servi) |
| [`Transversal/CatalogPhotoJourneyTest.php`](tests/Feature/Transversal/CatalogPhotoJourneyTest.php) | **La photo d'une annonce** : dépôt par le partenaire → carte du catalogue → fiche → file de validation du back-office |
| [`Transversal/BookingReviewJourneyTest.php`](tests/Feature/Transversal/BookingReviewJourneyTest.php) | Réservation confirmée → clôture (tâche planifiée ou check-out) → avis → modération |
| [`Pro/ProviderMissionJourneyTest.php`](tests/Feature/Pro/ProviderMissionJourneyTest.php) | Mission confiée → terminée → avis enfin possible |
| [`Build/ConstructionRequestJourneyTest.php`](tests/Feature/Build/ConstructionRequestJourneyTest.php) | Demande de chantier → alerte de l'équipe → dossier au back-office → visible côté client |

⚠️ **Pourquoi cette catégorie existe** : tous les défauts corrigés en F8.15/F8.16
vivaient **entre** deux couches, chacune verte de son côté. Un test de couche ne peut
structurellement pas les voir. ⚠️ `MoneyJourneyTest` **fait passer le temps**
(`travel`) plutôt que de figer les délais : la fin du service et les 7 jours de
sûreté sont des règles d'argent, les neutraliser rendrait le test aveugle à leur
inversion.

---

### Identifiants et prise de contrôle de compte (F8.22)

⚠️ **L'adresse de connexion est une serrure, pas un champ de profil.** Elle
commande `POST /auth/password/forgot` : qui la contrôle peut se faire envoyer un
nouveau mot de passe et prendre le compte. `PATCH /users/me` exige donc
`current_password` **dès qu'elle change** — comme `PATCH /users/me/password` le
fait depuis F3.2b. Avant cette tranche, une session ouverte suffisait à déplacer
l'adresse d'un **super administrateur**, puis à s'approprier le compte sans
jamais connaître son mot de passe.

Trois garanties accompagnent le changement, à ne pas défaire :

| Garantie | Pourquoi |
| --- | --- |
| `current_password` exigé | Une session ouverte (poste déverrouillé, jeton dérobé) ne doit pas suffire |
| **L'ANCIENNE adresse** est prévenue (`LoginEmailChangedNotification`) | Rend une prise de contrôle détectable. Prévenir la nouvelle informerait l'attaquant de sa réussite |
| Les **autres sessions** sont fermées | Un jeton dérobé ne doit pas survivre au changement qu'il a permis |

⚠️ **Exception : les comptes Google** (`google_id` non nul) en sont dispensés —
leur mot de passe est une chaîne aléatoire posée à la création (`Str::password`),
qu'ils n'ont jamais vue. Le leur réclamer les empêcherait de corriger leur
adresse.

⚠️ **L'e-mail d'alerte n'a pas d'interrupteur** dans les réglages du back-office
(contrairement aux alertes internes de F7.2.l) : c'est un e-mail de sécurité.

---

## Documentation

| Document | Contenu |
| --- | --- |
| [`API.md`](API.md) | Référence des 261 endpoints (accès, contrôleurs) |
| [`PERFORMANCE.md`](PERFORMANCE.md) | Index, cache, N+1, tests de charge |
| [`CONFIDENTIALITE.md`](CONFIDENTIALITE.md) | RGPD, rétention par type de donnée |
| [`app/Support/README.md`](app/Support/README.md) | Contrat d'API (enveloppe, erreurs, cache) |
| [`app/Support/Mail/README.md`](app/Support/Mail/README.md) | **E-mails transactionnels** : gabarit de marque, rédaction, aperçu navigateur |
| [`app/Support/Notifications/README.md`](app/Support/Notifications/README.md) | Canal SMS (Orange/Sonatel, Twilio) |
| `app/Modules/<Module>/README.md` | Logique métier de chaque module |
| [`app/Enums/README.md`](app/Enums/README.md) · [`app/Models/README.md`](app/Models/README.md) | Enums & modèles transverses |

Le code est **abondamment commenté en français**.

---

## État d'avancement

- ✅ **Backend (B0 → B17) : code-complet.** Authentification, 11 modules métier,
  couches transversales, paiement, sécurité/RGPD, notifications, durcissement &
  performance, documentation.
- ✅ **Intégrations (B18) :** webhooks sortants signés vers **n8n** (automatisation
  WhatsApp — voir [`WEBHOOKS.md`](WEBHOOKS.md)) et canal **SMS Orange/Sonatel**
  (`OrangeSmsProvider`), testés via `Http::fake`.
- ✅ **Connexion Google (B19) :** `POST /auth/google` (flux ID token, find-or-create).
- ✅ **Paiement manuel (B20) :** mode `manuel` sur `POST /payments/initiate`
  (règlement Wave/Orange Money au numéro officiel, sans PSP) + confirmation
  admin `POST /admin/payments/{payment}/confirm` — Phase 1 du cahier des charges.
- ✅ **Assistant transverse (F10.0 — socle) :** module `Assistant`, endpoint unique
  `POST /assistant/messages`, trousse à outils assemblée par rôle, cerveau
  interchangeable (`RuleBasedBrain` par défaut, `ClaudeBrain` prévu en F10.4) et
  garde-fous (débit dédié, plafonds d'entrée, interrupteur). **Hors cahier des
  charges** — voir [`app/Modules/Assistant/README.md`](app/Modules/Assistant/README.md).
  - ✅ **F10.1 (branchement du panneau Angular)** — deux correctifs côté serveur,
    trouvés en interrogeant le **serveur réel** : le lien « Voir mes messages »
    de l'escalade était écrit en dur sur `/mon-espace`, adresse gardée par le rôle
    `client` (un propriétaire y était refoulé) → il passe désormais par
    **`SpaceLink`**, comme les e-mails transactionnels ; et le vocabulaire de
    reconnaissance des lieux ignorait les **zones touristiques** et les
    **destinations** des circuits, alors que la recherche sait filtrer dessus —
    « Saly », « Casamance », « Gorée » n'étaient jamais transmis et l'assistant
    répondait n'importe où dans le pays en ayant l'air d'avoir compris.
  - ✅ **F10.2 (espaces connectés)** — 5 outils de consultation de SES propres
    dossiers (`mes_reservations`, `mes_demandes`, `mes_biens`, `mes_missions`,
    `mes_projets_diaspora`), tous en **lecture seule** et tous adossés à
    `PersonalRecordsTool`. ⚠️ Le cloisonnement y est **recopié du contrôleur HTTP**
    qui sert le même écran, jamais réécrit : c'est la parade au piège de
    `provider_missions.provider_id`, qui pointe sur `providers` et non sur
    `users`. **Journalisation** : aucune conversation stockée — seules les
    escalades remontent, via un sujet de fil préfixé « Assistant — ».
  - ✅ **F10.3 (back-office, lecture seule)** — 6 outils adossés à `BackOfficeTool`
    (`Tools/BackOffice/`) : `activite_plateforme`, `file_validation`,
    `demandes_a_traiter`, `fils_support`, `rechercher_compte`, `suivre_paiement`.
    ⚠️ **La trousse s'y assemble par PERMISSION, pas par rôle** : depuis F7.1.b le
    back-office délègue dossier par dossier, et un outil ouvert au seul rôle
    rendrait cette matrice décorative. `isAvailableFor()` interroge `can()`, donc
    **deux agents de la même équipe n'ont pas le même assistant** — et le super
    administrateur, qui n'a aucune permission assignée, reçoit la trousse complète
    par `Gate::before` (piège de F7.4.a). Deux gardes recopiées de l'existant :
    `file_validation` répond à l'*accès* (consulter n'est pas modérer, et filtrer
    plus finement donnerait un compteur **menteur**), `fils_support` à
    `repondre:messages`, portée par le rôle depuis F8.12.b. La règle d'aiguillage
    passe **avant toutes les autres** et n'est active que pour le staff, sinon
    « support » ferait escalader un agent qui demande sa propre boîte.
  - 🐛 **Défaut PRÉEXISTANT corrigé dans la foulée de F10.3 (CDC §6, module 1)** :
    `DashboardAggregator` comptait **quatre** types en attente de validation alors
    que `ValidatorRegistry` en porte **cinq** — les départs programmés y sont entrés
    en F8.23 sans jamais rejoindre l'agrégat. Le tableau de bord sous-comptait donc
    en silence (10 au lieu de 15 sur la base de développement), et un départ a une
    date de péremption. `mobility_services_pending` ajouté, test du dashboard
    étendu. ⚠️ **Toute entrée neuve du registre doit être ajoutée à l'agrégateur.**
- ⏳ **Actions client / déploiement** (hors code) : compte marchand PayTech +
  sandbox, souscription de la SMS API Orange + essai sandbox, URL/secret n8n,
  worker de queue supervisé.
- 🔜 **Frontend Angular** (chantier séparé).

---

## Licence

Projet propriétaire — Kaikun 360. Tous droits réservés.
