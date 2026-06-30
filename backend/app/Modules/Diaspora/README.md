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

> 🔜 À venir : endpoints (création/suivi), affectation d'agent, rapports,
> priorisation back-office et policy (B8.2).
