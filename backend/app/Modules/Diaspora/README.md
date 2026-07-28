# Module Diaspora — Projets pilotés à distance

Un membre de la diaspora pilote depuis l'étranger un **achat**, une **construction**
ou une **gestion locative**, accompagné par un **agent dédié**. Le suivi s'appuie
sur le modèle transversal `App\Models\Report` (commun au module Build).

---

## Projets diaspora (phase B8.1)

### Table `diaspora_projects`

| Champ | Rôle |
|---|---|
| `reference` (unique) | identifiant lisible (`DSP-…`) |
| `client_id` | client diaspora |
| `agent_id` | agent dédié (affecté ensuite, nullable) |
| `project_type` | nature — enum `DiasporaProjectType` (`achat`, `construction`, `gestion_locative`) |
| `residence_country` | pays de résidence |
| `budget_xof` | budget (facultatif) |
| `description` | descriptif libre |
| `priority` | enum `DiasporaPriority` (`normale`, `haute`, `strategique`) |
| `status` | enum `DiasporaProjectStatus` (`nouveau` → `en_cours` → `termine` / `annule`) |

### Modèle `DiasporaProject`

- `belongsTo` client et `agent` (User), `morphMany` `reports` (Report polymorphe).
- Casts enums/entier ; `DiasporaPriority::weight()` pour le tri back-office.

---

## Endpoints, affectation, rapports & policy (phase B8.2)

| Méthode | URL | Accès |
|---|---|---|
| POST | `/api/v1/diaspora-projects` | client — dépôt (statut `nouveau`) |
| GET | `/api/v1/diaspora-projects/mine` | client — mes projets |
| GET | `/api/v1/diaspora-projects/{id}` | client propriétaire, agent affecté ou admin (policy `view`) |
| GET | `/api/v1/diaspora-projects` | back-office (`can:consulter:dashboard-admin`) — **priorisé** ; filtres `status`/`priority`/`q` (F7.2.i) |
| PATCH | `/api/v1/diaspora-projects/{id}` | agent affecté ou admin (policy `update`) — **statut et/ou priorité, sans effet de bord** (F7.2.i) |
| PATCH | `/api/v1/diaspora-projects/{id}/assign` | admin (policy `assign`) — agent explicite ou auto (bascule « en cours ») |
| GET | `/api/v1/diaspora-projects/{id}/reports` | lecture (policy `view`) |
| POST | `/api/v1/diaspora-projects/{id}/reports` | agent affecté ou admin (policy `update`) |

- **Policy** `DiasporaProjectPolicy` : `view` = client/agent affecté/admin ;
  `update` (rapports) = agent affecté ou admin ; `assign` = admin. Enregistrée
  dans `AppServiceProvider`.
- **Attribution** `AgentAssignmentService` : agent explicite, ou — à défaut —
  l'agent le **moins chargé** (moins de projets actifs) ; passe le projet
  `en_cours`. Charge calculée par requête (pas de relation sur `User`).
- **Priorisation** : `index` back-office trie par priorité (stratégique > haute >
  normale) puis récence, et accepte les filtres `status` / `priority` / `q`
  (référence, pays de résidence, nom du client) — F7.2.i.
- **Pilotage back-office (F7.2.i)** : `update` (`PATCH /diaspora-projects/{id}`,
  `UpdateDiasporaProjectRequest`) modifie le **statut** et/ou la **priorité** SANS
  effet de bord (contrairement à `/assign`), ce qui permet de (re)prioriser un
  dossier avant toute affectation et de le clôturer/annuler. La `DiasporaProjectResource`
  expose `client` + `agent` (chargés dans `index`/`show`) pour la file/fiche back-office.
- **Rapports** : réutilisent le modèle transversal `App\Models\Report` et
  `ReportResource` (partagés avec Build).
