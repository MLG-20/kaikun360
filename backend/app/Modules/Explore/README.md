# Module Explore — Tourisme & expériences

Un prestataire publie des **circuits / expériences touristiques** (validés par un
agent avant mise au catalogue). Les clients réservent des places (capacité par
circuit) via le modèle transversal `Booking`.

---

## Expériences touristiques (phase B6.1)

### Table `tourism_experiences`

| Champ | Rôle |
|---|---|
| `reference` (unique) | identifiant lisible (`EXP-…`) |
| `provider_id` | prestataire à l'origine |
| `title` / `destination` | intitulé et lieu |
| `description` | descriptif libre |
| `duration_days` | durée du circuit (jours) |
| `price_xof` | tarif **par personne** |
| `capacity` | nombre total de places du circuit |
| `inclusions` (json) | inclusions structurées (`restauration`, `guide`, `transport`…) |
| `status` | modération — enum `ExperienceStatus` (défaut `en_attente_validation`) |
| `published_at` / `approved_by` | traçabilité de validation |

### Modèle `TourismExperience` (`app/Modules/Explore/Models/`)

- `belongsTo` provider (User), `morphMany` `bookings` (Booking polymorphe).
- Scope `published()` (= statut `publie`).
- Casts : `inclusions` array, `status` enum, entiers, `published_at` datetime.

### Enum `ExperienceStatus`

`en_attente_validation` → `publie` (+ `suspendu`, `rejete`).

> 🔜 À venir : catalogue public + publication prestataire & validation agent
> (B6.2), réservation/capacité (B6.3), annulation & remboursement (B6.4).
