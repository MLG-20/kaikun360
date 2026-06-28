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

---

## Catalogue, publication & validation (phase B6.2)

### Endpoints

| Méthode | URL | Accès |
|---|---|---|
| GET | `/api/v1/experiences` | public — catalogue (publiées), filtres destination/prix/durée/q/sort |
| GET | `/api/v1/experiences/{id}` | public — détail (404 si non publiée) |
| POST | `/api/v1/experiences` | prestataire **vérifié** (policy `create`) — statut `en_attente_validation` |
| GET | `/api/v1/experiences/mine` | prestataire — mes expériences (tous statuts) |
| PATCH | `/api/v1/experiences/{id}/approve` | agent (`can:valider:experience`) → `publie` |
| PATCH | `/api/v1/experiences/{id}/reject` | agent — `rejete` (motif facultatif, audité) |

- **Policy** `ExperiencePolicy` : `create` = prestataire/entreprise **au profil
  vérifié** (`verification_status = verifie`) ; `update` = prestataire propriétaire
  ou admin. Enregistrée dans `AppServiceProvider`.
- Enum `App\Modules\Core\Enums\ProfileVerificationStatus` (formalise non_verifie/
  en_cours/verifie/rejete) ; états `ProfileFactory::prestataire()` / `verifie()`.
- `ExperienceResource` (expose `seats_left` quand calculé). `StoreExperienceRequest`.

---

## Réservation & capacité (phase B6.3)

| Méthode | URL | Accès |
|---|---|---|
| GET | `/api/v1/experiences/{id}/availability` | public — capacité & places restantes |
| POST | `/api/v1/experiences/{id}/bookings` | auth — réservation de groupe |

- `ExperienceBookingService` : `seatsTaken` / `seatsLeft` / `canAccommodate`
  (places restantes = capacité − participants des réservations **non annulées** ;
  le `guests` d'un panier groupe occupe plusieurs places).
- La réservation crée un `Booking` polymorphe (`status` `en_attente`, montant =
  `guests × price_xof`, fin déduite de `duration_days`) ; refus si dépassement de
  capacité (422). Une expérience non publiée n'est pas réservable (404).

> 🔜 À venir : annulation & éligibilité au remboursement (B6.4).
