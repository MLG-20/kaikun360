# Kaikun 360 — Backend (API Laravel)

> Plateforme tout-en-un de l'immobilier, du tourisme et des services au Sénégal :
> achat/vente & location, nuitées, gestion locative, construction, tourisme &
> expériences, mobilité, diaspora, team building, marketplace de prestataires —
> le tout servi par une **API REST modulaire** en Laravel.

API backend du projet **Kaikun 360**. Ce dépôt contient l'application serveur
(Laravel). Le frontend (Angular) fait l'objet d'un chantier séparé.

- **177 endpoints** REST versionnés (`/api/v1`) — voir [`API.md`](API.md)
- **11 modules** métier isolés
- **55 tables**, référentiel géographique du Sénégal inclus
- **518 tests** automatisés (1513 assertions), tous verts ✅

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

Le code est découpé en **11 modules** indépendants, qui correspondent aux univers
et aux espaces de la plateforme :

- **Core** = les comptes, la connexion, les rôles (la porte d'entrée) ;
- **Immo, Stay, Manage, Build, Explore, Mobility, Diaspora, TeamBuilding, Pro** =
  les 9 univers métier (voir [Domaines fonctionnels](#domaines-fonctionnels)) ;
- **Admin** = le back-office, les coulisses de l'équipe Kaikun.

Chaque module a son propre `README.md` qui explique sa logique. Chaque fichier de
code est **abondamment commenté en français**.

### Où en est le moteur ?

**Il est terminé** (tous les univers, la sécurité, les paiements, les
notifications) et **vérifié par 518 tests automatiques** — des petits programmes
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
├── Support/          # ApiResponse, Settings, CatalogCache, Payments/…
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
    └── Admin/        # Back-office (dashboard, files de validation, supervision)
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
- **Immo** — catalogue public filtrable, dépôt de biens, validation par un agent,
  documents, comparaison (les favoris sont devenus transversaux, tous univers).
- **Stay** — catalogue de nuitées, disponibilité, réservation anti-double-booking,
  caution ; check-in/out & ménage côté back-office.
- **Manage** — mandats de gestion, loyers, incidents, dépenses, reversements
  propriétaires, rapport mensuel.
- **Build** — simulateur de coût de construction, jalons de chantier, rapports
  photo/vidéo polymorphes.
- **Explore** — expériences touristiques, capacité, réservation, annulation/
  remboursement.
- **Mobility** — véhicules (avec contrôle de conformité assurance/pirogue),
  services de mobilité, commission & caution.
- **Diaspora** — projets pilotés par un agent (affectation auto au moins chargé),
  rapports d'avancement.
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

Couches **transversales** : demandes de service (machine à états stricte), devis
génériques, réservations polymorphes, **messagerie générique** (conversations à
participants + messages, réutilisable par tous les espaces), **favoris polymorphes**
(tous univers : bien, nuitée, véhicule, expérience, mobilité — voir
`App\Support\Favoritables`), médias (compression d'images), avis (réservés au
consommateur ayant consommé), notifications (e-mail/SMS asynchrones, canal
`database` in-app, WhatsApp click-to-chat), paiement (PayTech ou manuel Wave/Orange
Money).

---

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
├── routes/              # api.php (glob des modules) + transversal.php
├── tests/               # Feature/<Module> (PHPUnit)
├── API.md               # Référence des 177 endpoints
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

## Performance

- **Index** de base de données sur les colonnes de filtrage/tri des catalogues.
- **Cache Redis** des résultats de catalogue/recherche, avec invalidation
  automatique (versioning) sur écriture des modèles.
- **Eager loading** systématique (chasse aux N+1, garde-fous testés).
- **Benchmark** local reproductible : `php artisan catalog:benchmark`.

Détail : [`PERFORMANCE.md`](PERFORMANCE.md).

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
php artisan db:seed --class=ContentSeeder    # (optionnel, dev) contenu éditorial de démo
                                             # → FAQ publiée + pages À propos/légales (slugs) ; idempotent

# 4. Stockage privé (documents/médias)
php artisan storage:link

# 5. Lancer l'API
php artisan serve                 # http://127.0.0.1:8000
```

> Un **worker de queue** est nécessaire en production pour les notifications
> asynchrones (`php artisan queue:work`), supervisé (Supervisor/systemd).

---

## Configuration (.env)

Clés principales (voir `.env.example` pour la liste exhaustive) :

| Clé | Rôle |
| --- | --- |
| `DB_*` | Connexion MySQL |
| `REDIS_*` | Cache, sessions, files d'attente |
| `CACHE_STORE=redis` | Cache des catalogues |
| `QUEUE_CONNECTION=redis` | Notifications asynchrones |
| `SMS_PROVIDER` | Canal SMS (`log` par défaut, `twilio` en prod) |
| `PAYTECH_BASE_URL` / `PAYTECH_API_KEY` / `PAYTECH_SIGNING_KEY` | Paiement PayTech |
| `CORS_ALLOWED_ORIGINS` | Origines autorisées (front Angular) |

> **Aucun secret n'est versionné** : `.env` est ignoré par git ; seuls les
> `.env.example` (valeurs factices) sont suivis.

---

## Tests

Suite **PHPUnit** (pas Pest), base dédiée `kaikun360_test`. Les tests chargent un
**dump de schéma** (`database/schema/mysql-schema.sql`) pour accélérer le démarrage.

```bash
php artisan test
# 518 tests, 1513 assertions — verts
```

> Après toute nouvelle migration : régénérer le dump
> (`php artisan schema:dump`) pour garder les tests rapides.

---

## Documentation

| Document | Contenu |
| --- | --- |
| [`API.md`](API.md) | Référence des 177 endpoints (accès, contrôleurs) |
| [`PERFORMANCE.md`](PERFORMANCE.md) | Index, cache, N+1, tests de charge |
| [`CONFIDENTIALITE.md`](CONFIDENTIALITE.md) | RGPD, rétention par type de donnée |
| [`app/Support/README.md`](app/Support/README.md) | Contrat d'API (enveloppe, erreurs, cache) |
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
- ⏳ **Actions client / déploiement** (hors code) : compte marchand PayTech +
  sandbox, souscription de la SMS API Orange + essai sandbox, URL/secret n8n,
  worker de queue supervisé.
- 🔜 **Frontend Angular** (chantier séparé).

---

## Licence

Projet propriétaire — Kaikun 360. Tous droits réservés.
