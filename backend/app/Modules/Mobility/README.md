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

---

## Services de mobilité (phase B7.2)

### Table `mobility_services`

| Champ | Rôle |
|---|---|
| `reference` (unique) | identifiant lisible (`MOB-…`) |
| `provider_id` | prestataire |
| `vehicle_id` | véhicule affecté (facultatif) |
| `type` | enum `MobilityServiceType` (`navette`, `transfert`, `liaison`, `excursion`) |
| `departure` / `destination` | itinéraire (villes/lieux) |
| `departure_at` | départ programmé (facultatif) |
| `capacity` / `price_xof` | places et tarif par place |
| `status` | modération — enum `MobilityServiceStatus` |
| `published_at` / `approved_by` | traçabilité de validation |

### Modèle `MobilityService`

- `belongsTo` provider et `vehicle` (nullable), `morphMany` `bookings`.
- Scope `published()` ; casts enums/dates/int.

---

## Catalogue, publication, validation & conformité (phase B7.3)

### Endpoints

| Méthode | URL | Accès |
|---|---|---|
| GET | `/api/v1/vehicles` | public — recherche (publiés), filtres type/capacité/prix/chauffeur |
| GET | `/api/v1/vehicles/{id}` | public — détail (404 si non publié) |
| POST | `/api/v1/vehicles` | prestataire **vérifié** (policy) → `en_attente_validation` |
| GET | `/api/v1/vehicles/mine` | prestataire — mes véhicules |
| PATCH | `/api/v1/vehicles/{vehicle}` | prestataire propriétaire (policy `update`) |
| PATCH | `/api/v1/vehicles/{vehicle}/approve` | agent (`can:valider:vehicule`) — bloqué si conformité incomplète |
| PATCH | `/api/v1/vehicles/{vehicle}/reject` | agent — `rejete` (motif facultatif) |
| GET | `/api/v1/mobility-services` | public — recherche par type/ville/date |

### Events & file de validation

- `VehicleCreated` → `NotifyAgentsOfNewVehicle` (notifie `valider:vehicule`).
- `VehicleValidated` → `NotifyProviderOfVehicleValidated` (le véhicule apparaît
  dans la recherche). Enregistrés dans `AppServiceProvider`.

### Conformité (`VehicleComplianceChecker`)

`missingFields()` bloque la validation tant que les champs requis manquent :
- **Motorisé** : `insurance_ref` (assurance), `capacity`, + `driver_identity` si
  chauffeur inclus.
- **Pirogue** : `capacity`, `life_jackets_count` (gilets), `weather_compliant`,
  `provider_compliant`.

### Policy

`VehiclePolicy` : `create` = prestataire/entreprise **vérifié** ; `update` =
prestataire propriétaire ou admin (enregistrée dans `AppServiceProvider`).

> 🔜 À venir : commission (`CommissionCalculator`) & caution sur réservation (B7.4).
