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

> Distinct des **payouts prestataires / PSP** (ledger, phases B11/B14).

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
