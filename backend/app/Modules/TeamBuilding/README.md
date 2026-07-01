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

---

## Devis composés multi-prestataires (phase B9.2)

### Table `team_building_quotes`

| Champ | Rôle |
|---|---|
| `reference` (unique) | identifiant lisible (`TBQ-…`) |
| `request_id` | demande rattachée |
| `lines` (json) | lignes (catégorie, libellé, module, quantité, prix unitaire, montant) |
| `subtotal_xof` / `margin_rate` / `margin_xof` / `total_xof` | totaux figés |
| `status` | enum `TeamBuildingQuoteStatus` (`brouillon` → `envoye` → `accepte`/`refuse`) |
| `sent_at` / `accepted_at` | horodatages métier |

### Service `TeamBuildingQuoteComposer`

- `buildLines(components)` : normalise chaque composant en ligne (montant =
  quantité × prix unitaire). Catégories via enum `QuoteLineCategory` (lieu,
  hébergement, restauration, activité, mobilité, animation) → agrège plusieurs
  modules (Stay/Manage, Explore, Mobility, animation).
- `totals(lines, marginRate)` : sous-total + **marge** (`DEFAULT_MARGIN_RATE`
  15 %) + total.
- `composeFor(request, components, marginRate?)` : persiste un devis `brouillon`.

> 🔜 À venir : endpoints + events (TeamBuildingRequestCreated, QuoteSent,
> QuoteAccepted) + policy (B9.3).
