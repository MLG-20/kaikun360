# Module Build — Construction / rénovation / devis

Un client décrit un projet de **construction**, **rénovation** ou **extension** ;
Kaikun l'étudie, propose un devis (couche transversale Quotes, phase B11) et
assure le **suivi de chantier** (rapports photo/vidéo, jalons).

---

## Demandes de construction (phase B5.1)

### Table `construction_requests`

| Champ | Rôle |
|---|---|
| `reference` (unique) | identifiant lisible de la demande (`CST-…`) |
| `client_id` | client à l'origine de la demande |
| `objective` | nature du projet — enum `ConstructionObjective` |
| `city` | ville du projet |
| `surface_m2` | surface visée (m²) |
| `budget_xof` | budget annoncé par le client (facultatif) |
| `finish_level` | niveau de finition — enum `FinishLevel` |
| `description` | descriptif libre |
| `estimated_cost_xof` | estimation indicative du simulateur (B5.4) |
| `status` | avancement — enum `ConstructionRequestStatus` (défaut `soumise`) |

### Modèle `ConstructionRequest` (`app/Modules/Build/Models/`)

- `belongsTo` client (User).
- Casts : enums `objective`/`finish_level`/`status` + entiers (`surface_m2`,
  `budget_xof`, `estimated_cost_xof`).
- Recevra `reports()` (B5.2), `milestones()` (B5.3) et les devis via Quotes (B11).

### Enums (`app/Modules/Build/Enums/`)

- `ConstructionObjective` : `construction_neuve`, `renovation`, `extension`.
- `FinishLevel` : `economique`, `standard`, `premium` (coefficient simulateur).
- `ConstructionRequestStatus` : `soumise` → `en_etude` → `devis_envoye` →
  `acceptee` → `en_chantier` → `terminee` (+ `annulee`).

---

## Rapports de suivi de chantier (phase B5.2)

### Table `reports` (polymorphe)

| Champ | Rôle |
|---|---|
| `reference` (unique) | identifiant lisible (`RPT-…`) |
| `reportable_type` / `reportable_id` | cible polymorphe (Construction ou Diaspora B8) |
| `created_by` | auteur du rapport (agent de suivi), facultatif |
| `type` | enum `ReportType` : `photo`, `video`, `mixte` |
| `photos` (json) | liste de chemins (disque privé) |
| `video_url` | URL de la vidéo éventuelle |
| `comment` | commentaire de chantier |
| `reported_at` | date du constat |

### Modèle transversal `App\Models\Report`

- `morphTo` `reportable` (suit la convention du modèle transversal `Booking`).
- `belongsTo` author (User, `created_by`).
- Casts : `type` enum, `photos` en tableau, `reported_at` date.
- Côté Build : `ConstructionRequest::reports()` (`morphMany`).

---

## Jalons de chantier (phase B5.3)

### Table `construction_milestones`

| Champ | Rôle |
|---|---|
| `construction_request_id` | demande rattachée |
| `name` | libellé de l'étape |
| `position` | ordre d'exécution |
| `status` | enum `MilestoneStatus` : `a_venir`, `en_cours`, `termine` |
| `planned_date` / `actual_date` | dates prévisionnelle et réelle |

### Modèle & relation

- `ConstructionMilestone` : `belongsTo` constructionRequest, casts dates/enum.
- `ConstructionRequest::milestones()` (`hasMany`, ordonné par `position`).

### Service `ConstructionMilestoneService`

- `defaultStagesFor(objective)` : découpage type (le parcours « neuf »/« extension »
  comporte fondations + toiture ; la « rénovation » commence par un diagnostic).
- `seedDefault(request)` : matérialise les jalons par défaut (statut `a_venir`),
  **idempotent** (ne duplique pas si des jalons existent déjà).

---

## Simulateur de budget (phase B5.4)

Service `ConstructionEstimator` (logique pure, sans base) :

- `estimate(objective, surfaceM2, finishLevel)` → coût total indicatif (XOF).
- `breakdown(...)` → détail structuré (prix au m², coefficient, total).

Règles : coût de base au m² selon l'objectif (neuf 250 000 > extension 220 000 >
rénovation 150 000), × coefficient de finition (éco 0,85 / standard 1,0 /
premium 1,35) × surface, arrondi au pas de 100 000 XOF.

> ⚠️ Estimation **non contractuelle** ; le devis ferme relève des Quotes (B11).

> 🔜 À venir : endpoints (création, mes demandes, rapports, simulation) +
> policy client (B5.5).
