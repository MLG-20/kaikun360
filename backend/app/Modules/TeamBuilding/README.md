# Module Team Building — Packs groupe entreprise

Une entreprise décrit un besoin de **team building** ; Kaikun compose un **devis
multi-prestataires** (lieu + hébergement + restauration + activité + mobilité +
animation) agrégeant plusieurs modules.

> ℹ️ Le devis est ici **scopé au module** (`TeamBuildingQuote`). La couche
> transversale **Quotes (B11)** généralisera la notion de devis (état, polymorphe).

---

## Demandes de team building (phase B9.1)

### Table `team_building_requests`

| Champ | Rôle |
|---|---|
| `reference` (unique) | identifiant lisible (`TBR-…`) |
| `company_id` | entreprise à l'origine |
| `participants` | nombre de participants |
| `city` | ville de l'événement |
| `start_date` / `end_date` | dates |
| `budget_xof` | budget indicatif (facultatif) |
| `needs` (json) | besoins structurés (hébergement, restauration, activité, mobilité, animation) |
| `description` | descriptif libre |
| `status` | enum `TeamBuildingRequestStatus` (`nouveau` → `en_etude` → `devis_envoye` → `accepte` / `annule`) |

### Modèle `TeamBuildingRequest`

- `belongsTo` company (User), `hasMany` `quotes` (TeamBuildingQuote, B9.2).
- Casts : `needs` array, dates, `participants`/`budget_xof` entiers, `status` enum.

> 🔜 À venir : devis composés multi-prestataires (B9.2) ; endpoints + events
> (TeamBuildingRequestCreated, QuoteSent, QuoteAccepted) + policy (B9.3).
