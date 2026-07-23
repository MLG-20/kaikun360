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
justificatifs fournis à l'inscription.

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
| GET | `/api/v1/providers/availability` | prestataire — planning hebdo + indispos à venir (F5.4) |
| PUT | `/api/v1/providers/availability/weekly` | prestataire — enregistre le planning hebdo (F5.4) |
| POST | `/api/v1/providers/availability/unavailability` | prestataire — ajoute une indispo (F5.4) |
| DELETE | `/api/v1/providers/availability/unavailability/{id}` | prestataire — supprime une indispo (F5.4) |
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

### Policy

`ProviderPolicy` : un utilisateur gère son propre profil ; les admins y ont accès.

---

## Missions & commission (phase B10.3)

### Table `provider_missions`

`provider_id`, `client_id?`, `title`, `description`, `amount_xof`,
`commission_xof`, `status` (enum `MissionStatus`), `scheduled_at`.

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

> Notation prestataire : colonnes `rating_avg`/`rating_count` **remplies en B12.3**
> par `App\Services\RatingAggregator` à la publication d'un avis (agrégation des
> avis publiés sur les véhicules et expériences du prestataire).
