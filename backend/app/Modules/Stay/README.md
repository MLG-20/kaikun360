# Module Stay — Nuitées / hébergements courte durée

Permet de proposer un **bien** (module Immo) en location **à la nuitée**.

---

## Couche de données (phase B3.1)

### Table `stays` (1–1 avec `properties`)

| Champ | Rôle |
|---|---|
| `property_id` (unique) | le bien proposé en nuitées |
| `price_per_night_xof` | prix par nuit (XOF) |
| `caution_xof` | dépôt de garantie |
| `capacity` | nombre de personnes |
| `min_nights` / `max_nights` | contraintes de séjour |
| `rules` / `amenities` (JSON) | règles de la maison / équipements |
| `check_in_time` / `check_out_time` | horaires |
| `is_active` | activation par le propriétaire |

### Modèle `Stay` (`app/Modules/Stay/Models/`)

- `belongsTo` Property.
- Scope **`bookable()`** : nuitées réellement réservables = `is_active = true`
  **et** bien sous-jacent `publie`. Centralise la règle de visibilité.

> 📅 La **disponibilité** (calendrier) et les **réservations** (table `bookings`
> polymorphe, anti double-réservation) arrivent en **phase B3.3**.
> La caution est stockée ici ; sa **retenue/restitution** (remboursement PayTech)
> relève des phases B11/B14.
