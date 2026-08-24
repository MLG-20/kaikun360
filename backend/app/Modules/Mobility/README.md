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
| `caution_xof` | caution exigée — ⚠️ **toujours 0 depuis le 2026-08-24 (F5.8)**, la caution reste réservée à la gestion locative (`properties.caution_xof`, module Immo) ; le champ de saisie a été retiré du formulaire prestataire le 2026-08-23 et les valeurs déjà enregistrées remises à 0 (`clear_caution_xof_on_vehicles_table`) |
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
| `reference` (unique) | identifiant lisible (`TRJ-…` depuis F8.23 ; `MOB-…` pour les lignes seedées) |
| `provider_id` | prestataire |
| `vehicle_id` | véhicule affecté (facultatif) |
| `type` | enum `MobilityServiceType` (`navette`, `transfert`, `liaison`, `excursion`) |
| `departure` / `destination` | itinéraire (villes/lieux) |
| `departure_at` | départ programmé (facultatif) |
| `capacity` / `price_xof` | places et tarif par place |
| `status` | modération — enum `MobilityServiceStatus` (+ `retire` depuis F8.23) |
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
| DELETE | `/api/v1/vehicles/{vehicle}` | prestataire propriétaire — retrait (F8.19) |
| GET | `/api/v1/mobility-services` | public — recherche par type/ville/date |
| GET | `/api/v1/mobility-services/{id}` | public — **fiche d'un départ + son remplissage** (F8.10) |
| POST | `/api/v1/mobility-services` | prestataire **vérifié** (policy) → `en_attente_validation` (F8.23) |
| GET | `/api/v1/mobility-services/mine` | prestataire — mes départs, tous statuts (F8.23) |
| PATCH | `/api/v1/mobility-services/{mobility_service}` | prestataire propriétaire (policy `update`) (F8.23) |
| DELETE | `/api/v1/mobility-services/{mobility_service}` | prestataire propriétaire — retrait (F8.23) |

### ⚠️ F8.23 — les départs programmés étaient en LECTURE SEULE

Depuis B7.2 et jusqu'à F8.23, `mobility_services` n'avait **aucune route
d'écriture** : ni prestataire ni agent ne pouvait créer un départ. Le catalogue
public `/mobilite` ne pouvait donc être alimenté **que par le seeder** — aucune
navette AIBD, aucune liaison interurbaine, aucun transfert n'était mettable en
vente en production. Tout l'aval était pourtant branché (fiche F8.10,
réservation de places B7.4, commission F8.4, reversement F8.16.a).

**Un départ n'est pas un véhicule** : un même minibus assure une navette le lundi
et une liaison le mardi. Ce qui se vend est le **trajet daté** ; le véhicule n'en
est que le moyen — et la source de ses photos (F8.18).

Trois règles propres au départ, absentes du cycle d'un véhicule :

1. **Le véhicule rattaché doit être le vôtre**, et sa capacité plafonne les
   places mises en vente (`MobilityServiceRequest::verifierLeVehicule()`). Sans
   cela, un prestataire illustrait son annonce avec le minibus d'un concurrent.
2. **La capacité ne descend jamais sous les places déjà vendues**
   (`UpdateMobilityServiceRequest`) : annuler des réservations est une décision
   commerciale, pas l'effet de bord d'un champ corrigé.
3. **Un départ passé ne se publie pas** — et s'il est opéré par un véhicule non
   conforme, il ne se publie pas non plus (`MobilityServiceValidator::approve()`).

### ⚠️ F8.23.a — un départ passé quittait le catalogue… mais restait payable

Défaut **préexistant** (B7.2/B7.4), resté hors d'atteinte tant que rien ne créait
de départ, et **monnayable** : éprouvé sur le serveur réel, une réservation de
**75 128 F** a été acceptée sur un départ parti trois semaines plus tôt, avec une
`start_date` dans le passé et une commission calculée.

- `MobilityService::scopeAVenir()` — le **catalogue public** n'expose plus les
  départs passés (`MobilityServiceController::index()`).
- `MobilityServiceBookingController::store()` **refuse** (422) une place sur un
  départ dont l'heure est passée.
- ⚠️ **La fiche affichait pourtant « ce départ a déjà eu lieu » depuis F8.10** :
  elle masquait le bouton, elle ne fermait pas la route. **Un écran ne protège
  rien** ; il évite seulement de proposer la faute.
- ⚠️ **Une date `NULL` reste visible et réservable, délibérément** :
  `departure_at` est nullable pour les services **à la demande** (navette
  affrétée, transfert sans horaire). Ils n'ont pas d'échéance à dépasser.
- ⚠️ Le **back-office continue de voir les départs passés** : c'est son
  historique d'exploitation (litige, reversement, réclamation).
- ⚠️ Le cache de catalogue est invalidé **à l'écriture**, pas au fil du temps :
  un départ qui périme entre deux écritures peut y survivre un TTL (5 min).
  Écart assumé — la réservation, elle, est refusée sur-le-champ.

### Events & file de validation

- `VehicleCreated` → `NotifyAgentsOfNewVehicle` (notifie `valider:vehicule`).
- `VehicleValidated` → `NotifyProviderOfVehicleValidated` (le véhicule apparaît
  dans la recherche). Enregistrés dans `AppServiceProvider`.
- **`MobilityServiceCreated` → `NotifyAgentsOfNewMobilityService`** (F8.23).
  L'e-mail porte la **date du départ** : un trajet validé après son heure ne se
  vend plus, c'est elle qui donne l'ordre de traitement de la file.
- La validation d'un départ passe par la **file générique** du back-office, type
  **`mobility_service`** (`MobilityServiceValidator`), permission
  `valider:vehicule` — **aucune permission neuve**. La file trie les départs par
  **échéance** et non par date de dépôt : seul type de la file à le faire.
  ⚠️ `mobility_service` est le premier type **composé** : la route
  `/admin/validate/{type}/{id}` le contraignait à `[a-z]+` et répondait **404**
  (contrainte élargie à `[a-z_]+` en F8.23).

### Conformité (`VehicleComplianceChecker`)

`missingFields()` bloque la validation tant que les champs requis manquent :
- **Motorisé** : `insurance_ref` (assurance), `capacity`, + `driver_identity` si
  chauffeur inclus.
- **Pirogue** : `capacity`, `life_jackets_count` (gilets), `weather_compliant`,
  `provider_compliant`.

### Policies

`VehiclePolicy` et **`MobilityServicePolicy`** (F8.23) portent les **mêmes
règles**, délibérément : `create` = prestataire/entreprise au profil **vérifié**,
`update` = propriétaire de l'offre ou admin. Deux règles divergentes obligeraient
le prestataire à comprendre pourquoi il peut publier un minibus mais pas la
navette qu'il opère avec. Enregistrées dans `AppServiceProvider`.

⚠️ **Le profil, pas le statut marketplace.** `create` lit
`profiles.verification_status`, que `ProviderValidationService` aligne quand un
agent valide un prestataire. Un prestataire créé **hors du parcours
d'inscription** (seeder, import) n'a pas de ligne `profiles` : la synchronisation
est alors un `?->` qui ne fait rien **en silence**, et le compte reste
définitivement incapable de publier (403 sans explication). C'était le cas de
tous les comptes de `DemoSeeder` — corrigé en F8.23.

### Supervision back-office (F7.2.j)

L'écran **Mobilité** du back-office est servi par le module **Admin**, pas par
celui-ci : `GET /admin/vehicles` (flotte + conformité + prestataire) et
`GET /admin/mobility-services` (départs tous statuts + remplissage). Deux points
à connaître si l'on touche à ce module :

- La grille de conformité affichée côté back-office **reprend celle du
  `VehicleComplianceChecker`** ci-dessus (motorisé = assurance + identité du
  chauffeur ; pirogue = gilets + météo + agrément). Faire évoluer le checker
  sans mettre à jour l'écran désynchroniserait les deux.
- `insurance_ref` et `driver_identity` ne sont **jamais** exposés par
  `VehicleResource` (catalogue public) : ce sont des données de contrôle,
  servies uniquement par `AdminVehicleResource` derrière les gardes admin.

---

## Réservation : commission & caution (phase B7.4)

| Méthode | URL | Effet |
|---|---|---|
| POST | `/api/v1/vehicles/{id}/bookings` | location (montant = jours × prix, **commission** figée, **caution retenue**) |
| PATCH | `/api/v1/vehicles/bookings/{booking}/cancel` | annulation (titulaire) — caution restituée/perdue selon le délai |
| POST | `/api/v1/mobility-services/{id}/bookings` | réservation de places (capacité + commission) |

> ⚠️ **F8.10 — les deux endpoints de réservation n'avaient aucun appelant.**
> Livrés en B7.3/B7.4, ils sont restés muets : le site ne proposait qu'un
> formulaire de *demande* (`POST /requests`) sur la fiche véhicule, et les
> trajets n'avaient **même pas de fiche** — `GET /mobility-services/{id}`
> n'existait pas, la carte du catalogue ne menait nulle part, et le code
> assumait que « la réservation d'un trajet se fait via un conseiller ».
> Les deux univers sont désormais réservables depuis le site.
>
> **Anti double-location ajouté au passage** : `POST /vehicles/{id}/bookings`
> ne vérifiait **aucun chevauchement** — deux clients pouvaient repartir avec le
> même véhicule le même jour. Même règle que les nuitées, à une nuance près :
> les bornes sont **incluses des deux côtés** (rendre et relouer le même jour,
> c'est la même journée de mise à disposition, alors qu'un départ de nuitée
> libère la nuit). Les statuts d'annulation sont **dérivés de l'enum**, jamais
> recopiés : une location annulée rend sa période au marché.
>
> Le **remplissage** (`seats_taken` / `seats_left`) accompagne la fiche d'un
> départ : afficher la capacité totale d'un trajet où il ne reste qu'une place
> ferait découvrir le refus après le clic. ⚠️ Ce n'est qu'un **affichage** — le
> contrôleur de réservation reste seul juge au moment d'écrire, deux clients
> pouvant viser la dernière place au même instant.

- `CommissionCalculator` : `commissionFor(montant, taux?)`, figé sur chaque
  réservation de mobilité (colonne `bookings.commission_xof`). ⚠️ **Déplacé en
  F8.4** de `Modules/Mobility/Services/` vers
  [`app/Support/Billing/`](../../Support/Billing/CommissionCalculator.php) : sept
  modules s'en servent désormais, un module métier n'a pas à importer une règle
  transverse depuis un module voisin. Le taux vient du back-office
  (`commission.default_rate`) ; `DEFAULT_RATE = 12 %` n'est qu'un **repli**
  appliqué tant que la direction n'a rien saisi.
- **Caution** (`bookings.caution_status`, enum `App\Enums\CautionStatus`) :
  `retenue` à la réservation ; à l'annulation, `restituee` si conforme
  (≥ `CANCEL_DELAY_DAYS = 2` jours avant le départ) sinon `perdue`.
- Annulation conforme → `refund.eligible = true` + montant ; **remboursement
  effectif via PayTech câblé en B14**.
- Colonnes `commission_xof` / `caution_status` ajoutées à la table transversale
  `bookings` ; `BookingResource` les expose.
