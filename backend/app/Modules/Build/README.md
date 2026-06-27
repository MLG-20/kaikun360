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

> 🔜 À venir : rapports de suivi polymorphes (B5.2), jalons de chantier (B5.3),
> simulateur de budget (B5.4), endpoints + policy client (B5.5).
