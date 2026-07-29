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

## Simulateur de budget (phase B5.4, enrichi « réalités sénégalaises »)

Service `ConstructionEstimator` :

- `estimate(objective, surfaceM2, finishLevel)` → coût des **travaux** seuls (XOF),
  au niveau RDC / zone Dakar (signature historique conservée pour le dépôt de
  demande).
- `breakdown(objective, surfaceM2, finishLevel, levels = 1, zone = dakar, landCostXof = 0)`
  → **détail complet** consommé par le frontend :
  - `works` : coût des travaux + répartition (gros œuvre / second œuvre /
    finitions) + échéancier par jalons ;
  - `fees` : frais annexes officiels (études & honoraires, permis, viabilisation) ;
  - `land` : foncier (le prix du terrain est **saisi**, jamais deviné) + frais
    d'acquisition (notaire/bornage/enregistrement) ;
  - `grand_total_xof`, `duration` (délai en mois), `rental` (rendement locatif
    indicatif longue durée / nuitée).

**Formule des travaux** : `prix_m² × (surface au sol × niveaux) × coeff finition ×
coeff zone`, arrondi au pas.

### 🔑 Barème piloté par les réglages (`build.pricing`)

Tous les **coefficients monétaires** (prix au m², finitions, zones, taux de frais,
taux d'acquisition foncière, rendements) vivent dans le réglage **`build.pricing`**
({@see `App\Support\SettingsRepository::DEFAULTS`}, type `json`, groupe
`construction`). Les valeurs du code sont des **ordres de grandeur par défaut** ;
l'**équipe admin les remplace par de vrais chiffres d'experts BTP** via le
back-office (`PATCH /admin/settings`), **sans redéploiement**. Une surcharge
**partielle** est acceptée (fusion récursive sur les défauts). L'estimateur est
donc la **source unique** du calcul, alignée en direct sur les réglages.

La répartition des travaux, l'échéancier et le délai sont **structurels**
(méthodologie) et restent en code.

> ⚠️ Estimation **non contractuelle** ; le devis ferme relève des Quotes (B11).
> Le endpoint `POST /construction-requests/simulate` est **public** (pur calcul,
> aucune donnée personnelle) pour alimenter la page Construction du site.

---

## Endpoints & policy (phase B5.5)

### Espace client (auth)

| Méthode | URL | Effet |
|---|---|---|
| POST | `/api/v1/construction-requests` | dépose une demande (estimation auto + jalons par défaut, statut `soumise`) |
| POST | `/api/v1/construction-requests/simulate` | simulation de budget (sans persistance) |
| GET | `/api/v1/construction-requests/mine` | mes demandes (paginées, `reports_count`) |
| GET | `/api/v1/construction-requests/{id}` | détail + jalons (policy `view`) |
| GET | `/api/v1/construction-requests/{id}/reports` | rapports de suivi (policy `view`) |

### Suivi de chantier (agents, permission `gerer:chantiers`)

| Méthode | URL | Effet |
|---|---|---|
| POST | `/api/v1/construction-requests/{id}/reports` | publie un rapport (`created_by` = agent) |
| POST | `/api/v1/construction-requests/{id}/milestones` | ajoute un jalon (F7.3.e1 ; `position` omise = fin de planning) |
| PUT | `/api/v1/construction-requests/{id}/milestones/reorder` | réécrit l'ordre du planning (liste ordonnée d'identifiants) |
| PATCH | `/api/v1/construction-milestones/{id}` | fait avancer / replanifie un jalon |
| DELETE | `/api/v1/construction-milestones/{id}` | retire un jalon du planning |

- **Policy** `ConstructionRequestPolicy` : `create` = tout authentifié ; `view` =
  client propriétaire **ou** agent/admin (super_admin via Gate::before). Enregistrée
  dans `AppServiceProvider`.
- **Permission** `gerer:chantiers` ajoutée au rôle agent (+ admin) dans le seeder.
- Resources : `ConstructionRequestResource` (jalons `whenLoaded`, `reports_count`
  `whenCounted`), `ConstructionMilestoneResource`, `ReportResource`.
- À la création : `ConstructionEstimator` renseigne `estimated_cost_xof` et
  `ConstructionMilestoneService` sème les jalons.

## Fiche dossier au back-office (F7.3.b)

L'écran back-office *Dossiers* (F7.2.e) ne montrait les demandes que sous forme
de tableau — illisible pour un dossier de chantier, dont l'essentiel (qui a
demandé quoi, où en est le chantier, ce qui a été constaté sur place) ne tient
pas dans une ligne. Une **fiche** consomme désormais les endpoints existants :

- `GET /construction-requests/{id}` — policy `view` (client propriétaire **ou**
  agent/admin). Charge le **client**, les **jalons triés par `position`** (l'ordre
  du chantier est ce que l'écran restitue) et le compte des rapports.
- `GET|POST /construction-requests/{id}/reports` — comptes rendus photo/vidéo ;
  la publication exige `gerer:chantiers`.

**Deux ajouts additifs** dans `ConstructionRequestResource`, sans migration :

- **`client`** (id, nom, e-mail, téléphone) via `whenLoaded` — le back-office
  pilote des dossiers, pas des lignes anonymes : sans le demandeur, l'agent ne
  peut ni comprendre ni rappeler. Même correctif qu'en F7.2.a sur la file de
  validation. `AdminDossierController::constructionRequests` eager-load donc
  `client` (anti-N+1), ce qui alimente aussi une colonne **Demandeur** dans la
  liste.
- **`created_at`** — un dossier de suivi se lit d'abord par son ancienneté.

## Pilotage des jalons (F7.3.e1)

Les jalons étaient semés au dépôt (`ConstructionMilestoneService::seedDefault()`)
puis **figés** : aucun endpoint ne les touchait, alors que « jalons chantier » est
une fonction explicite du CDC §6. `ConstructionMilestoneController` comble ce trou
et sert les deux gestes du terrain — *faire avancer* (démarrer, achever, rouvrir)
et *replanifier* (ajouter, renommer, redater, réordonner, retirer), car aucun
chantier ne suit exactement le gabarit posé à la création.

Trois règles portées par le serveur, à ne pas dupliquer côté client :

- **Cohérence statut ↔ date réelle.** Passage à `termine` sans `actual_date` → date
  du jour ; retour à `a_venir`/`en_cours` → `actual_date` **effacée**. Sans cela un
  jalon rouvert resterait « en cours, achevé le … ».
- **Réordonnancement par liste ordonnée d'identifiants**, en transaction, positions
  réécrites `1..n`. Envoyer une position par jalon produirait un doublon transitoire
  et un ordre indéterminé si la seconde écriture échouait.
- **Ordre refusé en bloc (422)** si un identifiant n'appartient pas au chantier :
  un planning à moitié réordonné est pire qu'un refus.

Un `PATCH` sans aucun champ est refusé (422) plutôt que de renvoyer un succès qui
n'a rien changé. La suppression **ne recompacte pas** les positions restantes :
l'affichage trie par `position`, un trou est invisible, et recompacter obligerait à
réécrire toute la liste à chaque retrait.

Tests : `tests/Feature/Build/ConstructionMilestonePilotageTest.php` (9 cas — dont
refus au client, effacement de la date à la réouverture, ordre étranger refusé).
Aucune migration : la table `construction_milestones` existe depuis B5.3.

> ⚠️ **Deux manques du CDC §6 *Construction* restent ouverts, côté serveur :**
> 1. **Devis** — ⚠️ **correction d'un audit antérieur** : `ConstructionRequest::quotes`
>    **n'existe pas**. La table `quotes` (B11.3) pend sur `requests` (les demandes de
>    contact génériques), pas sur `construction_requests` : il n'y a donc rien à
>    « rebrancher », le devis de chantier est à créer (prévu F7.3.e2, sur le motif
>    des devis pack du team building).
> 2. **Prestataires BTP** — aucun rattachement mission ↔ chantier, contrairement
>    au team building (`provider_missions.team_building_request_id`, F7.2.h) qui
>    fournit le motif à reprendre (prévu F7.3.e3).
>
> ⚠️ **Piège de test** : les jalons sont semés par le **contrôleur** `store`, pas
> par la factory — une demande créée directement par le modèle n'en a aucun.
