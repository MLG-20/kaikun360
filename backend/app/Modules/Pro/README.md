# Module Pro — Marketplace prestataires

Formalise le **prestataire marketplace** : inscription avec documents de
certification, validation par un agent, charte qualité (avertissements/sanctions),
missions affectées et commission. La validation d'un prestataire synchronise le
`verification_status` de son profil (Core), ce qui débloque la publication de
services publics (Explore B6, Mobility B7).

---

## Profil prestataire & certifications (phase B10.1)

### Table `providers` (1–1 avec `users`)

| Champ | Rôle |
|---|---|
| `user_id` (unique) | utilisateur prestataire |
| `business_name` | raison sociale |
| `category` | enum `ProviderCategory` |
| `bio` | présentation |
| `status` | enum `ProviderStatus` (`en_attente`/`valide`/`refuse`/`suspendu`) |
| `validated_at` / `validated_by` | traçabilité de validation |
| `warnings_count` / `sanction_note` | charte qualité |
| `rating_avg` / `rating_count` | note agrégée (remplie par Reviews, B12) |

### Table `provider_certifications`

`provider_id`, `name`, `issuer`, `file_path` (disque privé), `verified` — les
justificatifs fournis à l'inscription. Depuis **F8.0**, la table porte aussi les
métadonnées du fichier, alignées sur `user_documents` : `disk`, `original_name`,
`mime_type`, `size`.

> ⚠️ **Dette soldée en F8.0.** `file_path` existait depuis B6 mais n'était
> **jamais renseignée** : aucun contrôleur n'acceptait de fichier, « Mes
> services » se contentait de *déclarer* une certification (nom + organisme).
> Symptôme visible au back-office (Comptes → Documents) : une **colonne fichier
> structurellement vide** pour toutes les certifications, alors que le CDC §6
> les compte parmi les « pièces prestataires » à contrôler. L'écran affichait
> d'ailleurs `file_path` — un nom aléatoire généré par Laravel, illisible pour
> l'agent — d'où l'ajout d'`original_name`.

### Modèles

- `Provider` : `belongsTo` user, `hasMany` certifications + missions (B10.3) +
  weeklyAvailabilities + unavailabilities (F5.4), helper `isValidated()`, casts enums.
- `ProviderCertification` : `belongsTo` provider.
- `ProviderWeeklyAvailability` (F5.4) : `belongsTo` provider ; un jour du planning
  (`weekday` 0 = lundi … 6 = dimanche, `is_open`, `start_time`/`end_time`).
- `ProviderUnavailability` (F5.4) : `belongsTo` provider ; période d'absence
  (`start_date` → `end_date`, `reason`).

### Enums

- `ProviderStatus` : `en_attente` → `valide` (+ `refuse`, `suspendu`).
- `ProviderCategory` : restauration, animation, guide, transport, événementiel,
  artisanat, autre.

---

## Inscription, validation & charte qualité (phase B10.2)

| Méthode | URL | Accès |
|---|---|---|
| POST | `/api/v1/providers` | auth — inscription (rôle+profil prestataire, statut `en_attente`) |
| GET | `/api/v1/providers/mine` | auth — mon profil prestataire |
| PUT | `/api/v1/providers/mine` | prestataire — édite son descriptif de service (« Mes services ») |
| POST | `/api/v1/providers/certifications` | prestataire — ajoute une certification (toujours non vérifiée) |
| DELETE | `/api/v1/providers/certifications/{id}` | prestataire — supprime une de ses certifications |
| GET | `/api/v1/providers/availability` | prestataire — planning hebdo + indispos à venir (F5.4) |
| PUT | `/api/v1/providers/availability/weekly` | prestataire — enregistre le planning hebdo (F5.4) |
| POST | `/api/v1/providers/availability/unavailability` | prestataire — ajoute une indispo (F5.4) |
| DELETE | `/api/v1/providers/availability/unavailability/{id}` | prestataire — supprime une indispo (F5.4) |
| GET | `/api/v1/providers/reviews` | prestataire — mes avis reçus + synthèse de notation (F5.5) |
| PATCH | `/api/v1/providers/{id}/validate` | agent (`can:valider:prestataire`) → `valide` |
| PATCH | `/api/v1/providers/{id}/reject` | agent → `refuse` |
| PATCH | `/api/v1/providers/{id}/suspend` | agent → `suspendu` (motif) |
| PATCH | `/api/v1/providers/{id}/warn` | agent — avertissement (charte qualité) |

### Synchronisation validation ↔ profil (règle « non validé = pas de publication »)

`ProviderValidationService` pilote le `verification_status` du profil (Core) :
- `validate` → profil `verifie` → **débloque** la publication (Explore B6, Mobility B7) ;
- `reject`/`suspend` → profil `rejete`/`non_verifie` → **bloque** la publication.

C'est ainsi que la règle « un prestataire non validé ne publie aucun service
public » est réalisée de bout en bout (testée par intégration).

### Charte qualité

`warn()` incrémente `warnings_count` ; au-delà de `SUSPENSION_THRESHOLD` (3) le
prestataire est suspendu d'office. `sanction_note` conserve le motif.

### « Mes services » — édition par le prestataire (F5)

`ProviderProfileController` porte la **mise à jour** du dossier par le prestataire
lui-même (l'inscription initiale reste `ProviderRegistrationController@store`).
Tout est scopé au profil du compte connecté via le helper privé `providerFor()`
(`Provider::where('user_id', …)->firstOrFail()` → **404 si pas de profil**) ; il
n'y a donc pas de policy à invoquer.

- `update` (**PUT** `/providers/mine`) — raison sociale, catégorie, présentation.
  ⚠️ **Ne touche pas au statut de validation** : corriger un descriptif ne doit
  pas re-déclencher une revue back-office (couvert par un test dédié).
- `storeCertification` (**POST** `/providers/certifications`) — 201. La
  certification est créée avec `verified => false` **explicite** : le défaut SQL
  ne s'applique pas à l'instance renvoyée, la Resource sérialiserait `null`.
  Accepte depuis **F8.0** un **justificatif** (multipart, champ `file`) rangé
  sous `certifications/{provider_id}` sur le **disque privé**, avec ses
  métadonnées. Le champ `file` est retiré du tableau avant l'insertion : ce
  n'est pas une colonne, Eloquent tenterait d'écrire un `UploadedFile`.
- `destroyCertification` (**DELETE** `/providers/certifications/{id}`) —
  cloisonnement par `abort_unless($certification->provider_id === $provider->id, 404)`.
  Supprime **aussi le fichier** (`deleteFile()`) : le laisser serait conserver
  une pièce personnelle que plus rien ne référence.
- `downloadCertification` (**GET** `/providers/certifications/{id}/download`) —
  route **hors `auth:sanctum`**, protégée par le middleware `signed` : la
  signature fait foi, exactement comme pour les pièces KYC. L'URL est produite
  par `ProviderCertificationResource` et vaut **10 minutes**.

FormRequests : `UpdateProviderProfileRequest` (mêmes règles que l'inscription hors
certifications) et `StoreCertificationRequest` (`name` requis, `issuer` facultatif,
`file` facultatif en PDF/JPG/PNG ≤ 5 Mo ;
`verified` **n'est pas** acceptée en entrée — la vérification est une action agent).

> ⚠️ **Le justificatif reste facultatif — décision produit, pas un oubli.** Un
> prestataire doit pouvoir déclarer sa certification tout de suite et revenir
> déposer le scan une fois numérisé. `has_file` permet au back-office et à
> l'écran « Mes services » de distinguer « pas de pièce » de « pièce à
> contrôler » ; l'interface prévient qu'**une certification sans justificatif
> restera « En vérification »**, faute de quoi le prestataire attendrait une
> validation qui ne peut pas venir.
>
> ⚠️ **`download_url` ne fuit pas dans le catalogue public** : les certifications
> ne sont chargées (`whenLoaded`) que sur `/providers/mine`, l'inscription et les
> écrans admin. Toute nouvelle route publique qui ferait `load('certifications')`
> exposerait des liens de téléchargement — à vérifier avant d'en ajouter une.

Routes déclarées dans le groupe `providers` : segments **non numériques**
(`mine`, `certifications`) → aucune collision avec `/{provider}/…` (`whereNumber`).

Tests : `tests/Feature/Pro/ProviderProfileTest.php` (7 cas — mise à jour, statut
inchangé, catégorie invalide 422, ajout non vérifié, suppression, suppression
d'autrui 404, compte sans profil 404) et
`tests/Feature/Pro/ProviderCertificationFileTest.php` (8 cas — dépôt du fichier,
pièce facultative, format refusé, téléchargement signé, URL non signée 403, URL
expirée 403, suppression du fichier, nom visible au back-office).

### Policy

`ProviderPolicy` : un utilisateur gère son propre profil ; les admins y ont accès.

---

## Missions & commission (phase B10.3)

### Table `provider_missions`

`provider_id`, `client_id?`, `title`, `description`, `amount_xof`,
`commission_xof`, `status` (enum `MissionStatus`), `scheduled_at`,
`team_building_request_id?` (F7.2.h), `construction_request_id?` (F7.3.e3),
`category?`.

> **Missions rattachées à un dossier.** Le team building et la construction ont
> tous deux besoin d'« affecter des prestataires » (CDC §6). Plutôt que de
> dupliquer la notion, ces modules **rattachent une mission** à leur dossier via
> une clé étrangère facultative : la mission garde son cycle, sa commission figée
> et sa remontée dans les revenus du prestataire. Les affectations passent alors
> par leurs propres endpoints (`…/team-building-requests/{id}/assignments`,
> `…/construction-requests/{id}/assignments`), pas par celui ci-dessous.
>
> ⚠️ **`category` est PARTAGÉE** : brique de pack pour une mission team building,
> **lot BTP** (`ConstructionLot`) pour une mission de chantier. C'est la clé
> étrangère renseignée qui dit quel vocabulaire lire. Une mission ordinaire a les
> trois colonnes à `null` — les deux migrations sont purement additives.

| Méthode | URL | Accès |
|---|---|---|
| POST | `/api/v1/providers/{provider}/missions` | admin (policy `assignMission`) — prestataire **validé** requis |
| GET | `/api/v1/provider-missions/mine` | prestataire — mes missions |
| GET | `/api/v1/provider-missions/earnings` | prestataire — synthèse revenus & commissions (F5.3) |
| PATCH | `/api/v1/provider-missions/{mission}/{action}` | prestataire affecté — `accept`/`refuse`/`start`/`complete` |

- **Commission** figée à l'affectation via `CommissionCalculator` (réutilisé du
  module Mobility, B7).
- Affectation refusée (422) si le prestataire n'est pas validé.
- Transitions contrôlées : `affectee → acceptee → en_cours → terminee`
  (`refuse` depuis `affectee`) ; toute transition invalide renvoie 422.
- **`earnings`** (F5.3) agrège les missions du prestataire connecté : réalisé
  (missions `terminee`), à venir (`acceptee` + `en_cours`) et nombre de missions
  à traiter (`affectee`) ; le net = montant − commission.

---

## Avis reçus (F5.5)

L'écran « Avis reçus » du prestataire réunit **deux sources d'avis** dans une même
notation :

1. les avis publiés sur ses **ressources** (véhicules, expériences) — déjà
   agrégés en B12.3 ;
2. les avis **directs** sur le prestataire lui-même, déposés par le client d'une
   **mission terminée** — nouveau canal branché ici.

### Branchement du canal direct

- `Review::TYPES` gagne la clé `provider` → `Provider::class` : un prestataire
  devient une cible notable au même titre qu'un véhicule ou une expérience.
- `ReviewPolicy::create` distingue la cible : pour un `Provider`, l'éligibilité
  est `Review::hasCompletedMissionWith($user, $provider)` (une `ProviderMission`
  `terminee` dont l'utilisateur est le client) au lieu de la réservation terminée.
- `RatingAggregator` réunit les deux sources en **une seule requête**
  (`receivedReviewsQuery`), utilisée à la fois pour recalculer `rating_avg`/
  `rating_count` **et** pour alimenter l'écran → la note de tête et la liste ne
  peuvent pas diverger. `providerUserIdFor` reconnaît désormais un `Provider`
  noté directement (retourne son `user_id`).

### Endpoint

`ProviderReviewController@index` (**GET** `/providers/reviews`) — scopé au profil
du compte connecté via `providerFor()` (**404 si pas de profil**). Renvoie la
`summary` (moyenne, total, `distribution` 5★→1★ calculés en direct) et la liste
des avis (`ProviderReviewResource` : ajoute un libellé `source` — « Prestation
directe » ou le nom de la ressource notée).

Tests : `tests/Feature/Pro/ProviderReviewTest.php` (7 cas — réunion des sources,
libellé « Prestation directe », exclusion d'un autre prestataire, 404 sans profil,
dépôt d'avis direct après mission terminée, refus 403 sans mission terminée,
report de la note à la publication).

> Notation prestataire : colonnes `rating_avg`/`rating_count` **remplies en B12.3**
> par `App\Services\RatingAggregator` à la publication d'un avis, en agrégeant les
> avis publiés sur les véhicules et expériences du prestataire **ainsi que les
> avis directs** le notant (F5.5).
