# Module Explore — Tourisme & expériences

Un prestataire (ou, depuis F21, l'équipe Kaikun elle-même via `Gate::before`)
publie des **circuits / expériences touristiques** (validés par un agent avant
mise au catalogue). Les clients réservent des places sur une **date de départ**
du circuit via le modèle transversal `Booking`.

---

## Expériences touristiques (phase B6.1, revue F21)

### Table `tourism_experiences`

| Champ | Rôle |
|---|---|
| `reference` (unique) | identifiant lisible (`EXP-…`) |
| `provider_id` | prestataire à l'origine (ou l'admin qui l'a déposé, F21) |
| `title` / `destination` | intitulé et lieu |
| `description` | descriptif libre |
| `itinerary` (json) | programme jour par jour (`[{day, title, description}, …]`) |
| `duration_days` | durée du circuit (jours) |
| `price_xof` | tarif **par personne** |
| `included` / `excluded` (texte libre) | ce qui est / n'est pas compris dans le prix |
| `status` | modération — enum `ExperienceStatus` (défaut `en_attente_validation`) |
| `published_at` / `approved_by` | traçabilité de validation |

### Table `tourism_experience_departures` (F21)

Un circuit a **plusieurs dates de départ**, chacune avec ses **propres
places** — remplace la colonne `capacity` unique (et les 4 `inclusions` à
cocher, remplacées par `included`/`excluded` ci-dessus).

| Champ | Rôle |
|---|---|
| `tourism_experience_id` | circuit propriétaire |
| `start_date` | date de départ |
| `seats_total` | places de CETTE date |

⚠️ **Aucune clé étrangère depuis `bookings`** : une réservation porte sa propre
`start_date` (comme tout `Booking`), le rapprochement avec une date de départ se
fait par égalité de date (`ExperienceBookingService`). Conséquence directe :
retirer une date de départ n'affecte jamais une réservation déjà prise — c'est
`ExperienceDepartureSyncer` qui interdit ce retrait tant que la date porte des
réservations non annulées, en garde-fou métier plutôt qu'en contrainte SQL.

### Modèle `TourismExperience` (`app/Modules/Explore/Models/`)

- `belongsTo` provider (User), `morphMany` `bookings` (Booking polymorphe),
  `hasMany` `departures` (`TourismExperienceDeparture`, triées par date).
- Scope `published()` (= statut `publie`).
- Casts : `itinerary` array, `status` enum, entiers, `published_at` datetime.

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
| PATCH | `/api/v1/experiences/{id}` | prestataire propriétaire ou admin — édition + sync des dates de départ |
| GET | `/api/v1/experiences/mine` | prestataire — mes expériences (tous statuts) |
| PATCH | `/api/v1/experiences/{id}/approve` | agent (`can:valider:experience`) → `publie` |
| PATCH | `/api/v1/experiences/{id}/reject` | agent — `rejete` (motif facultatif, audité) |

- **Policy** `ExperiencePolicy` : `create` = prestataire/entreprise **au profil
  vérifié** (`verification_status = verifie`) ; `update` = prestataire propriétaire
  ou admin. Enregistrée dans `AppServiceProvider`. Le **`super_admin` passe outre
  via `Gate::before`** (F21) : le back-office peut ainsi déposer un circuit
  lui-même — `provider_id` devient alors le compte admin — quand aucun
  prestataire n'en a encore proposé. Le formulaire est le même que celui du
  prestataire (`provider-experience-form-page`, monté aussi sous
  `/back-office/tourisme/circuit/nouveau`), pour qu'aucun second formulaire ne
  puisse diverger.
- Enum `App\Modules\Core\Enums\ProfileVerificationStatus` (formalise non_verifie/
  en_cours/verifie/rejete) ; états `ProfileFactory::prestataire()` / `verifie()`.
- `ExperienceResource` (expose `departures`, chacune avec `seats_left` quand
  calculé). `StoreExperienceRequest` exige **au moins une date de départ** — un
  circuit sans date ne serait jamais réservable. `UpdateExperienceRequest` +
  `ExperienceDepartureSyncer` gèrent l'ajout/correction/retrait des dates.

---

## Réservation & capacité (phase B6.3, revue F21)

| Méthode | URL | Accès |
|---|---|---|
| GET | `/api/v1/experiences/{id}/availability` | public — dates de départ à venir & places restantes |
| POST | `/api/v1/experiences/{id}/bookings` | auth — réservation de groupe sur une date |

> ⚠️ **F8.10 — endpoint sans appelant depuis B6.** La fiche d'un circuit ne
> déposait qu'une *demande*, avec une date « souhaitée » **facultative** et le
> nombre de participants recopié dans un message qu'un conseiller relisait à la
> main. Les places sont désormais réellement décomptées et le client est emmené
> payer.
>
> ⚠️ **F21 — la date n'est plus libre.** Le client choisissait n'importe quelle
> date (`start_date`, seule contrainte : pas dans le passé) sur un pot de places
> **global** au circuit — deux clients partant à des mois d'écart se disputaient
> la même capacité. Il choisit désormais un `departure_id` parmi les dates
> programmées par le prestataire/admin, chacune avec ses propres places.

- `ExperienceBookingService` : `seatsTaken` / `seatsLeft` / `canAccommodate`
  prennent une `TourismExperienceDeparture`, pas l'expérience entière (places
  restantes = places de CETTE date − participants des réservations **non
  annulées portant sa date** ; le `guests` d'un panier groupe occupe plusieurs
  places).
- La réservation crée un `Booking` polymorphe (`status` `en_attente`, montant =
  `guests × price_xof`, dates début/fin déduites de la date de départ choisie et
  de `duration_days`) ; refus si dépassement de capacité **de cette date** (422).
  Une expérience non publiée n'est pas réservable (404) ; une date de départ
  d'un autre circuit est refusée à la validation (422).

---

## Annulation & remboursement (phase B6.4)

| Méthode | URL | Accès |
|---|---|---|
| PATCH | `/api/v1/experiences/bookings/{booking}/cancel` | auth — titulaire uniquement |

- `ExperienceCancellationService` : éligibilité au remboursement si l'annulation
  intervient **au moins `REFUND_DELAY_DAYS` (7) jours avant le départ**. Renvoie
  `refund_eligible` + `refund_amount_xof` (montant total si éligible, sinon 0).
- L'annulation met le statut à `annulee_client` (libère les places) ; un tiers
  est refusé (403), une réservation déjà annulée renvoie 422, une réservation
  non-expérience renvoie 404.

> 🔗 Le **remboursement effectif via PayTech** est déclenché en **B14** (ici on
> calcule seulement l'éligibilité et le montant).

### Localisation Google Maps (F5.10)

`tourism_experiences.maps_link` (nullable) porte le lien `src` d'une carte
Google Maps **intégrée** (mode de partage gratuit « Intégrer une carte », pas
l'API Maps Embed payante — voir `Immo/README.md`). Validé par
`App\Rules\GoogleMapsLink` (transversale), affiché via le composant partagé
`app-google-map-embed` sur la fiche du circuit.

---

## Supervision back-office (F7.2.k)

L'écran **Tourisme** du back-office est servi par le module **Admin**, pas par
celui-ci : `GET /admin/experiences` (circuits tous statuts + remplissage +
prestataire, via `AdminExperienceResource`) et `GET /admin/tourism/destinations`
(couverture agrégée par destination). Trois points à connaître :

- **La capacité par date de départ (F21)** : `capacity_total`/`seats_taken` sur
  la liste (`GET /admin/experiences`) sont des CUMULS toutes dates confondues
  (repère rapide, agrégés via `withSum('departures as capacity_total',
  'seats_total')`) ; la fiche (`GET /admin/experiences/{id}`) détaille en plus
  le remplissage **par date**, calculé avec `ExperienceBookingService` comme
  côté public.
- **Les destinations ne sont pas une entité** : c'est la colonne
  `tourism_experiences.destination`. La vue back-office les reconstruit par
  `GROUP BY` ; la capacité cumulée par destination vient d'une jointure séparée
  sur `tourism_experience_departures` (une jointure directe dans la requête
  groupée multiplierait les lignes). Modéliser un jour les destinations rendrait
  cet agrégat caduc.
- **Guides et restaurants ne sont pas modélisés ici** : le cahier des charges
  les cite (§6) mais le module ne les connaît que comme du texte libre dans
  `included`/`excluded` (F21). Les partenaires réels vivent dans le module
  **Pro** (catégories `guide` / `restauration`, table `provider_categories`
  depuis F5), et **aucun lien guide ↔ circuit n'existe** — écart au cahier des
  charges signalé à l'écran, à combler par un modèle d'affectation si le besoin
  se confirme.
- **Le dépôt n'est plus réservé aux prestataires (F21)** : un `super_admin` peut
  déposer un circuit depuis `/back-office/tourisme/circuit/nouveau` (même
  formulaire, même endpoint que le prestataire). Il ne peut ensuite le MODIFIER
  depuis le back-office que s'il en est lui-même le `provider_id` — un circuit
  d'un vrai prestataire se modifie depuis l'espace de ce dernier.

## Commission plateforme (F8.4)

Une réservation d'expérience fige la **commission Kaikun** dans
`bookings.commission_xof`, via
[`CommissionCalculator`](../../Support/Billing/CommissionCalculator.php) — même
taux paramétrable au back-office (`commission.default_rate`) que les autres
univers.

⚠️ **Ce n'était pas le cas avant F8.4** : la plateforme vendait des circuits sans
aucune trace de son revenu. Assiette = `participants × prix` ; commission **figée
à la réservation**, jamais recalculée ensuite.
