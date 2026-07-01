# Couche transversale — Requests, Quotes, Bookings (phase B11)

Modèles partagés par tous les modules métier (namespace `App\Models`), qui
unifient trois notions transverses :

- **`ServiceRequest`** (table `requests`) — demande client générique. Nommé ainsi
  pour ne pas heurter `Illuminate\Http\Request`. Suit une machine à états stricte.
- **`Quote`** (table `quotes`) — devis rattaché à une demande (B11.3).
- **`Booking`** (table `bookings`) — réservation polymorphe (introduite en B3.3,
  enrichie ici). `bookable` = Stay, Vehicle, TourismExperience, MobilityService…
- **`Report`** — rapport de suivi polymorphe (introduit en B5.2, partagé Build/Diaspora).

---

## Demandes génériques (phase B11.1)

### Table `requests`

| Champ | Rôle |
|---|---|
| `reference` (unique) | identifiant lisible (`REQ-…`) |
| `user_id` | demandeur |
| `service_type` | univers concerné — enum `App\Enums\ServiceType` |
| `message` | contenu de la demande |
| `budget_xof` / `city` | budget et ville (facultatifs) |
| `status` | machine à états — enum `App\Enums\RequestStatus` |
| `priority` | enum `App\Enums\RequestPriority` (`normale`/`haute`/`urgente`) |

### Machine à états stricte (`RequestStatus`)

```
recu → verification → visite → devis → negociation → cloture
```

- `allowedNext()` / `canTransitionTo()` : on avance **d'une étape à la fois** ; la
  **clôture** reste possible à toute étape (abandon/fin anticipée). Aucun retour en
  arrière ni saut d'étape. `cloture` est terminal.
- Le changement de statut (agents/admin) valide cette machine côté API (B11.2) :
  toute transition invalide est rejetée (422).

> 🔜 À venir : endpoints demandes + events (B11.2) ; quotes + finalisation
> bookings (B11.3).
