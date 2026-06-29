# Module Mobility — Transport & mobilité

Deux entités : les **véhicules** (`Vehicle`, loués avec/sans chauffeur) et les
**services de mobilité** (`MobilityService`, trajets programmés départ→destination).
Publiés par des prestataires, validés par un agent, réservables via `Booking`.

---

## Véhicules (phase B7.1)

### Table `vehicles`

| Champ | Rôle |
|---|---|
| `reference` (unique) | identifiant lisible (`VEH-…`) |
| `provider_id` | prestataire propriétaire |
| `type` | catégorie — enum `VehicleType` |
| `brand` / `model` | marque et modèle |
| `capacity` | nombre de places |
| `price_per_day_xof` | tarif journalier |
| `has_driver` | chauffeur inclus |
| `caution_xof` | caution exigée |
| `insurance_ref` / `driver_identity` | conformité **motorisé** (assurance, identité chauffeur) |
| `life_jackets_count` / `weather_compliant` / `provider_compliant` | conformité **pirogue** (gilets, météo, prestataire) |
| `status` | modération — enum `VehicleStatus` (défaut `en_attente_validation`) |
| `published_at` / `approved_by` | traçabilité de validation |

### Modèle `Vehicle`

- `belongsTo` provider (User), `morphMany` `bookings` (Booking) ; média en B12.
- Scope `published()` ; casts enums/bool/int.

### Enums

- `VehicleType` : `voiture_particuliere`, `voiture_touristique`, `navette_aibd`,
  `bus`, `minibus`, `quatre_quatre`, `pirogue`, `chauffeur` — helper `isMotorized()`
  (tout sauf la pirogue).
- `VehicleStatus` : `en_attente_validation` → `publie` (+ `suspendu`, `rejete`).

> 🔜 À venir : services de mobilité (B7.2) ; catalogue/publication/validation +
> events + règles de conformité + policy (B7.3) ; commission & caution (B7.4).
