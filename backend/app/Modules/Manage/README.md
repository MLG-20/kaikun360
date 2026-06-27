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

> 🔜 À venir : reversements (B4.4), tableau de bord & rapport mensuel (B4.5),
> endpoints CRUD + policy d'isolation (B4.6).
