# Couche transversale — Requests, Quotes, Bookings (phase B11)

Modèles partagés par tous les modules métier (namespace `App\Models`), qui
unifient trois notions transverses :

- **`ServiceRequest`** (table `requests`) — demande client générique. Nommé ainsi
  pour ne pas heurter `Illuminate\Http\Request`. Suit une machine à états stricte.
- **`Quote`** (table `quotes`) — devis rattaché à une demande (B11.3).
- **`Booking`** (table `bookings`) — réservation polymorphe (introduite en B3.3,
  enrichie ici). `bookable` = Stay, Vehicle, TourismExperience, MobilityService…
- **`Report`** — rapport de suivi polymorphe (introduit en B5.2, partagé Build/Diaspora).
- **`Media`** — média polymorphe (image compressée ou vidéo par URL) rattaché à une
  ressource illustrée (Property, Vehicle, TourismExperience…) (B12.1).

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

### Endpoints & events (phase B11.2)

| Méthode | URL | Accès |
|---|---|---|
| POST | `/api/v1/requests` | auth — créer une demande (event `RequestCreated`) |
| GET | `/api/v1/requests/my` | auth — mes demandes |
| PATCH | `/api/v1/requests/{request}/status` | agents/admin (`can:traiter:demandes`) — machine à états |

- **Permission** `traiter:demandes` (agent + admin) ajoutée au seeder.
- **Events** (enregistrés dans `AppServiceProvider`) :
  `RequestCreated` → `NotifyAvailableAgentsOfRequest` (agents disponibles) ;
  `RequestStatusChanged` → `NotifyUserOfRequestStatusChange` (notification **mise
  en file** → push/WhatsApp/email B16).
- Le changement de statut applique `canTransitionTo()` : toute transition invalide
  (saut d'étape, retour arrière, depuis `cloture`) est rejetée en **422**.
- `ServiceRequestResource` expose `allowed_transitions` (aide au frontend).

---

## Devis (phase B11.3)

### Table `quotes`

| Champ | Rôle |
|---|---|
| `reference` (unique) | identifiant lisible (`QTE-…`) |
| `request_id` | demande rattachée (`ServiceRequest`, cascade) |
| `amount_xof` | montant proposé |
| `details` (json) | lignes/conditions structurées (facultatif) |
| `valid_until` | date de validité (facultatif) |
| `status` | enum `App\Enums\QuoteStatus` (`brouillon`/`envoye`/`accepte`/`refuse`) |

### Endpoints & règles

| Méthode | URL | Accès |
|---|---|---|
| POST | `/api/v1/requests/{request}/quotes` | agents/admin (`can:traiter:demandes`) — crée un devis `envoye` |
| GET | `/api/v1/quotes/{quote}` | `QuotePolicy@view` — demandeur / agent / admin |
| PATCH | `/api/v1/quotes/{quote}` | `QuotePolicy@respond` — le **demandeur** accepte/refuse |

- Le demandeur ne peut répondre qu'à un devis **`envoye`** (ni brouillon, ni déjà
  tranché) : sinon rejet **422** sur le champ `status`.
- `QuotePolicy` enregistrée dans `AppServiceProvider`.

## Finalisation des réservations (phase B11.3)

- **Horodatage d'annulation** : le hook `Booking::booted()` (`saving`) fige
  automatiquement `cancelled_at` dès qu'un statut d'annulation (`estAnnulee()` :
  `annulee_client`/`annulee_prestataire`/`annulee_admin`) est posé. Distinct du
  statut de paiement. `BookingResource` expose `cancelled_at`.
- **`GET /api/v1/bookings/my`** (`BookingController@my`) : liste les réservations
  de l'utilisateur connecté.

---

## Médias (phase B12.1)

### Table `media`

| Champ | Rôle |
|---|---|
| `reference` (unique) | identifiant lisible (`MED-…`) |
| `mediable_type` / `mediable_id` | ressource illustrée (polymorphe) |
| `uploaded_by` | auteur du dépôt |
| `type` | enum `App\Enums\MediaType` (`image`/`video`) |
| `disk` / `path` | disque + chemin du fichier (image) |
| `url` | URL externe (vidéo) |
| `original_name` / `mime_type` / `size_bytes` | métadonnées |
| `is_primary` | image « de une » (une seule par ressource) |
| `position` | ordre d'affichage dans la galerie |
| `status` | enum `App\Enums\MediaStatus` (`actif`/`masque`) |

### Ressources autorisées & autorisation

- Seules les ressources listées dans **`Media::TYPES`** peuvent porter un média
  (clé courte exposée à l'API → FQCN) : `property`, `vehicle`, `experience`. On
  n'accepte jamais une classe arbitraire du client.
- **L'autorisation réutilise la policy `update` du module concerné** :
  `Gate::authorize('update', $mediable)` (PropertyPolicy / VehiclePolicy /
  ExperiencePolicy). Aucune logique de propriété dupliquée.

### Endpoints (B12.1)

| Méthode | URL | Accès |
|---|---|---|
| POST | `/api/v1/media/upload` | propriétaire de la ressource (policy `update`) |
| DELETE | `/api/v1/media/{media}` | idem ; média orphelin → admin uniquement |

- **Compression** : à l'upload, les images sont redimensionnées (largeur max
  1600 px) et recompressées en **JPEG q80** par `App\Services\ImageProcessor`
  (isolé pour un futur passage en Job de queue, B16), puis stockées sur le disque
  `public`. Les vidéos sont référencées par URL externe.
- **Image principale** : passer `is_primary=true` retire l'ancienne image de une
  de la même ressource (unicité garantie).

> ℹ️ Disque `public` : penser à `php artisan storage:link` en environnement réel
> pour exposer les fichiers (les tests utilisent `Storage::fake`).
