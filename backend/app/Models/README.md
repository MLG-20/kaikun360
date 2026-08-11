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
- **`Review`** — avis polymorphe (note 1–5 + commentaire, modéré) déposable
  uniquement par un consommateur avéré de la ressource (B12.2).

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
| `agent_id` | **F8.11** — l'agent qui a composé le devis (nullable, `nullOnDelete`) |
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

### F8.11 — accepter un devis crée une réservation payable

⚠️ **Le trou comblé.** Jusqu'à F8.11, `respond()` ne faisait que changer la
colonne `quotes.status`. Aucune réservation n'était créée, donc aucun montant
n'était exigible — et `POST /payments/initiate` réclamant un `booking_id`, le
client ne pouvait tout simplement pas régler ce qu'il venait d'accepter. Le
circuit du sur-mesure s'arrêtait sur un accord sans suite.

- **`App\Services\QuoteConversionService`** crée la réservation. Il est
  **idempotent** (verrou de ligne + `morphOne` existant) : un double clic ou un
  rejeu ne produisent jamais deux montants à payer pour une prestation.
- ⚠️ **Le devis accepté est LUI-MÊME la cible polymorphe** de la réservation
  (`bookable_type = Quote`). Aucune migration de `bookings` : la table est
  polymorphe depuis B3.3. Le sens est juste — le sur-mesure n'a aucune fiche au
  catalogue à désigner, ce qui est vendu c'est le devis.
- **Aucune notification depuis le service** : il tourne dans une transaction, un
  e-mail parti avant un `rollback` annoncerait une réservation inexistante.
  C'est le contrôleur qui notifie, après coup.
- **Commission figée à la conversion** (`CommissionCalculator`), même régime que
  tous les univers depuis F8.4.
- **`QuoteAnsweredNotification`** part au SEUL agent auteur (`agent_id`), jamais
  à toute l'équipe : sinon personne ne se sent responsable du dossier.
  Événement back-office `quote_answered` (coupable dans *Paramètres*).
- `BookingResource` connaît le type **`quote`** → libellé « Prestation
  sur-mesure », `item_label` tiré de l'univers de la demande d'origine
  (« Prestation — Gestion locative »). **Non annulable** par le client : sur du
  sur-mesure on en parle à son interlocuteur, on ne clique pas.

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

### Relations côté ressource — trait `Concerns\HasMedia` (F8.1)

Toute classe de `Media::TYPES` utilise le trait `App\Models\Concerns\HasMedia`,
qui expose **deux** relations volontairement distinctes :

| Relation | Contenu | Usage |
|---|---|---|
| `media()` | médias **actifs** seulement, `is_primary` en tête puis `position` | catalogue et fiche **publics** |
| `allMedia()` | **tous** les médias, masqués compris | **back-office** (modération) |

`allMedia()` ne doit jamais être exposée sur une route publique : elle contient
précisément ce qu'un agent a choisi de retirer des annonces. Réciproquement, un
écran de modération bâti sur `media()` empêcherait de **rétablir** une photo
masquée, qui aurait disparu de la vue de l'agent lui-même.

> Historiquement seul `Property` portait cette relation : `Vehicle` et
> `TourismExperience` acceptaient des dépôts via `POST media/upload` qu'aucun
> écran ne pouvait relire (dette « sera branchée en B12 »). Corrigé en F8.1.

### Endpoints (B12.1)

| Méthode | URL | Accès |
|---|---|---|
| POST | `/api/v1/media/upload` | propriétaire de la ressource (policy `update`) |
| DELETE | `/api/v1/media/{media}` | idem ; média orphelin → admin uniquement |
| PATCH | `/api/v1/admin/media/{media}/status` | **modération** (F8.1) : masquer / réafficher, permission de validation du type parent |

- **Compression** : à l'upload, les images sont redimensionnées (largeur max
  1600 px) et recompressées en **JPEG q80** par `App\Services\ImageProcessor`
  (isolé pour un futur passage en Job de queue, B16), puis stockées sur le disque
  `public`. Les vidéos sont référencées par URL externe. ⚠️ Ces bornes valent
  pour une image **vue en vignette** ; une image de **fond plein écran** en
  demande d'autres — `ImageProcessor::BACKGROUND_MAX_WIDTH` (2560 px / q88),
  utilisée par les bandeaux (F12).
- **Image principale** : passer `is_primary=true` retire l'ancienne image de une
  de la même ressource (unicité garantie).

> ℹ️ Disque `public` : penser à `php artisan storage:link` en environnement réel
> pour exposer les fichiers (les tests utilisent `Storage::fake`).

---

## Avis (phase B12.2)

### Table `reviews`

| Champ | Rôle |
|---|---|
| `reference` (unique) | identifiant lisible (`REV-…`) |
| `user_id` | auteur de l'avis |
| `reviewable_type` / `reviewable_id` | ressource notée (polymorphe) |
| `rating` | note 1–5 |
| `comment` | commentaire (facultatif) |
| `status` | enum `App\Enums\ReviewStatus` (`en_attente`/`publie`/`rejete`) |
| `moderated_by` / `moderated_at` | traçabilité de la modération (B12.3) |

- Contrainte d'unicité `(user_id, reviewable_type, reviewable_id)` : **un seul
  avis par utilisateur et par ressource**.

### Règle « avis réservé au consommateur »

- Ressources notables : **`Review::TYPES`** (`stay`, `vehicle`, `experience`) —
  ce sont des ressources **réservables** (`Booking` polymorphe).
- `ReviewPolicy@create` délègue à **`Review::hasConsumed($user, $reviewable)`** :
  vrai s'il existe une réservation `terminee` de l'utilisateur sur cette
  ressource. Une seule requête sur `bookings` couvre tous les types (polymorphe).

### Endpoints (B12.2)

| Méthode | URL | Accès |
|---|---|---|
| GET | `/api/v1/reviews?reviewable_type=&reviewable_id=` | public — avis **publiés** + note agrégée (`average`, `count`) |
| POST | `/api/v1/reviews` | auth + policy `create` (consommateur) — avis `en_attente` |

### Modération & notation prestataire (phase B12.3)

| Méthode | URL | Accès |
|---|---|---|
| PATCH | `/api/v1/reviews/{review}/moderate` | agents/admin (`ReviewPolicy@moderate`) — `publie`/`rejete` |

- On ne modère qu'un avis **`en_attente`** (pas de re-modération) : sinon 422.
  La modération trace `moderated_by` / `moderated_at`.
- **Report de la note** : à la publication, `App\Services\RatingAggregator`
  recalcule `providers.rating_avg` / `rating_count` du prestataire concerné, sur
  ses **avis publiés** uniquement.
- **Périmètre de l'agrégation** : seules les ressources détenues par un
  prestataire alimentent la note — **véhicules** (Mobility) et **expériences**
  (Explore), dont `provider_id` = utilisateur prestataire. Les **nuitées**
  (Stay), détenues par un propriétaire et non un prestataire au sens du module
  Pro, sont notables mais **hors agrégation** (aucune note prestataire associée).
