# Module Manage — Gestion locative

Kaikun gère des biens pour le compte de leurs propriétaires : **mandats**,
**loyers**, **incidents**, **dépenses** et **reversements**.

---

## Mandats de gestion (phase B4.1)

### Table `management_mandates`

| Champ | Rôle |
|---|---|
| `reference` (unique) | identifiant lisible du mandat |
| `property_id` | bien géré |
| `owner_id` | propriétaire du bien |
| `commission_rate` | taux de commission Kaikun (%) |
| `start_date` / `end_date` | durée du mandat |
| `status` | cf. enum `MandateStatus` |
| `terms` | conditions particulières |

### Modèle `ManagementMandate` (`app/Modules/Manage/Models/`)

- `belongsTo` Property et `belongsTo` owner (User).
- Enum `MandateStatus` : `en_attente`, `actif`, `suspendu`, `termine`.

---

## Loyers (phase B4.2)

### Table `rents`

Échéances de loyer d'un mandat. Champs clés : `mandate_id`, locataire
(`tenant_id` utilisateur **ou** `tenant_name` libre), `period_label`, `due_date`,
`amount_xof`, `status` (enum `RentStatus` : `impaye`, `paye`, `en_retard`), `paid_at`.

Modèle `Rent` : `belongsTo` mandate et tenant (User).
Relation `ManagementMandate::rents()` (hasMany).

---

## Incidents & dépenses (phase B4.3)

- **`incidents`** : signalements liés à un bien (`property_id`, `reported_by`,
  `title`, `priority` p1–p4, `status` via enum `IncidentStatus`
  ouvert/en_cours/resolu/clos, `resolved_at`). Modèle `Incident`.
- **`expenses`** : dépenses d'un bien (`property_id`, `incident_id` optionnel,
  `label`, `category` via enum `ExpenseCategory` maintenance/reparation/autre,
  `amount_xof`, `spent_at`, `receipt_path` sur disque privé). Modèle `Expense`.

> Les relations incidents/dépenses pointent vers le **bien** (`property_id`),
> ce qui facilitera l'agrégation par propriétaire (dashboard B4.5) sans coupler
> le module Immo au module Manage.

---

## Reversements au propriétaire (phase B4.4)

- **`owner_payouts`** : versement du produit de la gestion locative au
  propriétaire (`mandate_id`, `owner_id`, `period_label`, `amount_xof`,
  `status` via enum `OwnerPayoutStatus` en_attente/effectue, `paid_at`,
  `proof_path`). Modèle `OwnerPayout` ; relation `ManagementMandate::payouts()`.

> Distinct des **reversements aux partenaires** des autres univers, qui vivent
> dans les tables transverses `partner_dues` / `partner_payouts` (F8.16.a).
>
> ⚠️ **Pourquoi les deux coexistent, et pourquoi c'est voulu.** `mandate_id` est
> **non nullable** : cette table ne peut structurellement porter que la gestion
> locative. Surtout, elle reverse une **PÉRIODE** (loyers encaissés − commission
> − dépenses d'un mois), pas un service rendu — la comptabilité n'est pas la
> même. Les y fondre aurait aplati deux réalités distinctes ; le registre
> transverse couvre les quatre autres lignes d'affaires (nuitées, véhicules,
> circuits, trajets) plus les missions prestataires.
>
> ⚠️ **Dette connue, NON corrigée ici** : `owner_payouts.proof_path` existe
> depuis B4.4 et **rien ne l'écrit jamais** — aucun endpoint ne téléverse de
> justificatif, alors que l'écran Documents du back-office compte les preuves de
> reversement (il compte donc toujours zéro). Le registre F8.16.a ne refait pas
> cette promesse : son `POST .../pay` **exige** la pièce. Aligner la gestion
> locative dessus reste à faire.

---

## Espace propriétaire — lecture & tableau de bord (phase B4.5)

| Méthode | URL | Accès |
|---|---|---|
| GET | `/api/v1/manage/dashboard` | `auth` — KPIs agrégés du propriétaire |
| GET | `/api/v1/manage/mandates/mine` | `auth` — mes mandats (avec agrégats) |
| GET | `/api/v1/manage/mandates/{mandate}` | `auth` + policy `view` |

- **Isolation** : un propriétaire ne voit que ses propres mandats (scoping par
  `owner_id`). Le détail `{mandate}` passe par `ManagementMandatePolicy::view`
  (propriétaire **ou** agent/admin ; super_admin via Gate::before).
- **Agrégats** (sans N+1, via `withSum`/`withCount`) : loyers payés / impayés,
  dépenses, reversements effectués, incidents ouverts.
- `MandateResource` expose ces sommes sous `summary`.

> **F4.4 — lignes détaillées sur la fiche.** Pour l'espace propriétaire, la fiche
> `GET /manage/mandates/{mandate}` **eager-load** en plus les `rents`, `payouts`
> et `incidents` (les **12 plus récents** de chaque) ; `MandateResource` les
> expose sous les clés `rents` / `payouts` / `incidents` — **uniquement quand la
> relation est chargée** (`when(relationLoaded(...))`, jamais `whenLoaded` qui
> casserait `Resource::collection` sur une relation absente). La **liste** `mine`
> ne charge PAS ces relations : elle reste légère (agrégats seulement), les clés
> sont simplement absentes.

---

## Gestion par les agents & rapport mensuel (phase B4.6)

### Endpoints de gestion (permission `gerer:gestion-locative`)

Réservés aux **agents** (et admin/super_admin). La permission est portée par le
middleware `can:gerer:gestion-locative` (ajoutée au rôle `agent` dans le seeder).
Contrôleur : `MandateManagementController`.

| Méthode | URL | Effet |
|---|---|---|
| POST | `/api/v1/manage/mandates` | crée un mandat (`owner_id` **déduit** du bien) |
| POST | `/api/v1/manage/mandates/{mandate}/rents` | ajoute une échéance de loyer (statut `impaye`) |
| PATCH | `/api/v1/manage/rents/{rent}/pay` | marque le loyer `paye` (+`paid_at`) |
| POST | `/api/v1/manage/mandates/{mandate}/incidents` | signale un incident (`property_id` du mandat, `reported_by` = agent) |
| PATCH | `/api/v1/manage/incidents/{incident}/resolve` | marque l'incident `resolu` (+`resolved_at`) |
| POST | `/api/v1/manage/mandates/{mandate}/expenses` | enregistre une dépense (incident éventuel **du même bien**) |
| POST | `/api/v1/manage/mandates/{mandate}/payouts` | crée un reversement `en_attente` (`owner_id` du mandat) |
| PATCH | `/api/v1/manage/payouts/{payout}/pay` | marque le reversement `effectue` (+`paid_at`, audité) |

Les entrées sont validées par des Form Requests (`Store*Request`) ; les réponses
utilisent des Resources dédiées (`Rent/Incident/Expense/OwnerPayoutResource`).

### Rapport mensuel (lecture — propriétaire ou agent)

| Méthode | URL | Accès |
|---|---|---|
| GET | `/api/v1/manage/mandates/{mandate}/report?month=YYYY-MM` | `auth` + policy `view` |

`ManagementReportService::forMandate()` renvoie des **données structurées** (mois
par défaut = courant) : loyers payés/impayés, dépenses, **commission** (= loyers
encaissés × taux), **net propriétaire** (= encaissé − commission − dépenses),
reversements effectués, incidents ouverts/résolus. Bornage par mois calendaire.

> Export **PDF** reporté à une phase ultérieure ; le frontend consomme le JSON.

## Pilotage depuis le back-office (F7.3.a)

Les endpoints de gestion ci-dessus existaient depuis **B4.6** mais **aucune
interface ne les atteignait** : l'écran back-office *Dossiers* (F7.2.e) ne faisait
que superviser les mandats en lecture seule. Un agent ne pouvait donc ni encaisser
un loyer, ni clore un incident, ni enregistrer une dépense depuis l'application.
La **fiche mandat** du back-office (`/back-office/dossiers/mandats/:id`) les branche
tous — l'écart était **entièrement frontend**.

Deux manques **côté serveur** sont apparus en ouvrant la tranche, et sont comblés :

- **Les dépenses n'étaient pas relisables.** `POST .../expenses` les créait, mais
  `GET /manage/mandates/{mandate}` ne chargeait pas la relation et
  `MandateResource` ne l'exposait pas — la ligne CDC §6 les cite pourtant
  explicitement. Elles sont désormais eager-loadées (12 dernières, `spent_at`
  décroissant, même bornage que les autres lignes) et rendues par
  `ExpenseResource`. ⚠️ Rappel : `expenses` et `incidents` pointent vers le
  **bien** (`property_id`), pas vers le mandat — la relation passe par la colonne
  partagée.
- **Les clauses du mandat (`terms`) étaient invisibles.** Stockées depuis B4.6,
  jamais exposées : ce sont les « contrats » de la ligne CDC. `MandateResource`
  les rend maintenant.

Aucune migration, aucun endpoint neuf : la fiche consomme les routes existantes.

> ⚠️ **Permissions.** La *lecture* de la fiche passe par la policy `view`
> (propriétaire du mandat **ou** agent/admin) ; toute *écriture* exige
> `gerer:gestion-locative`. Un compte back-office sans cette délégation voit donc
> la fiche mais échoue en 403 sur les actions — l'écran l'explique au lieu
> d'afficher une erreur générique.

Effet de bord bienvenu : les **incidents deviennent résolvables**, ce qui referme
le dernier point ouvert du module CDC §6 *Avis et qualité* (les incidents y sont
rangés, mais l'écran Avis & qualité renvoie vers Dossiers — décision F7.2.g).
