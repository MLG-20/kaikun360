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

---

## Catalogue public (phase B3.2)

| Méthode | URL | Accès |
|---|---|---|
| GET | `/api/v1/stays` | public — liste filtrable & paginée |
| GET | `/api/v1/stays/{id}` | public — détail d'une nuitée réservable |

> 🔒 Seules les nuitées **réservables** (`Stay::bookable()` = active + bien
> publié) sont exposées ; sinon **404** au détail.

- Filtres : `region_id`, `department_id`, `commune_id` (portés par le bien),
  `capacity` (mini), `price_min`/`price_max` (par nuit), `q` (titre du bien), `per_page`.
- `StayResource` embarque le bien via la `PropertyResource` du module Immo.

---

## Disponibilité & réservation (phase B3.3)

| Méthode | URL | Accès |
|---|---|---|
| GET | `/api/v1/stays/{id}/availability` | public — créneaux déjà occupés |
| POST | `/api/v1/stays/{id}/bookings` | `auth:sanctum` + vérifié — réserver |

> ⚠️ **F8.10 — cet endpoint est resté sans appelant depuis B3.3.** La fiche
> publique d'une nuitée ne proposait qu'un formulaire de *demande*
> (`POST /requests`) : le visiteur croyait avoir réservé et « Mes réservations »
> lui répondait qu'il n'avait rien réservé. Le commentaire du composant Angular
> l'assumait — « la réservation ferme relève des phases ultérieures ». Ces
> phases n'étaient jamais venues. La fiche crée désormais un vrai séjour.

### Réservations (`bookings`, polymorphe)

- Table `bookings` **polymorphe** (`bookable`) introduite ici, **enrichie en B11**.
  Modèle transversal `App\Models\Booking` (statut via `App\Enums\BookingStatus`).
- Relation `Stay::bookings()` (morphMany).

### Règles vérifiées au moment de réserver

1. **Anti double-réservation** : refus si la période chevauche une réservation
   non annulée. Convention : `end_date` = jour de **départ exclusif**, donc des
   créneaux **adjacents** (départ = arrivée suivante) sont autorisés.
2. Capacité (`guests ≤ capacity`), séjour min/max (`min_nights`/`max_nights`),
   dates cohérentes (`StoreStayBookingRequest`).
3. Montant calculé = `nuits × prix/nuit` ; caution reprise depuis la nuitée.
   Statut initial `en_attente` (le paiement viendra en **B14**).

> 💰 Retenue/restitution de la **caution** (remboursement PayTech) : phases B11/B14.

---

## Gestion de la config nuitées par le propriétaire (phase F4.3)

Un bien peut être loué **au mois** (`price_xof` sur le bien), **à la nuitée**
(config `Stay`) ou **les deux** (« mixte »). Ces deux endpoints permettent au
propriétaire d'activer/paramétrer ou de retirer le mode nuitées de **son** bien.

| Méthode | URL | Accès |
|---|---|---|
| PUT | `/api/v1/properties/{property}/stay` | `auth:sanctum` + `verified.account` — upsert |
| DELETE | `/api/v1/properties/{property}/stay` | `auth:sanctum` — retrait |

- `StayManagementController` (`upsert` / `destroy`). **Autorisation réutilisant la
  `PropertyPolicy`** (`update`) : seul le propriétaire du bien (ou un admin) agit
  dessus — aucune `StayPolicy` distincte.
- **`upsert`** (idempotent, `updateOrCreate` sur `property_id`) : crée la config
  si absente (**201**), la met à jour sinon (**200**), et **réactive** (`is_active
  = true`) une config qui aurait été désactivée. Corps validé par
  `UpsertStayRequest` (seul `price_per_night_xof` est requis ; `max_nights ≥
  min_nights` vérifié).
- **`destroy`** : **supprime** la config s'il n'existe aucune réservation ; sinon
  la **désactive** (`is_active = false`) pour préserver l'intégrité de
  l'historique (le bien disparaît du catalogue nuitées, les réservations passées
  restent cohérentes). **404** si le bien n'a pas de config.
- La config nuitées est **embarquée dans `PropertyResource`** (clé `stay`) quand
  la relation est chargée — uniquement en gestion privée
  (`PropertyManagementController`), jamais au catalogue public (pas de N+1, clé
  absente). Alimente la fiche et le formulaire d'édition du propriétaire.

> ✅ Tests : `tests/Feature/Stay/PropertyStayManagementTest` (12 cas) couvre
> l'upsert (création/màj/réactivation), la validation, l'isolation entre
> propriétaires, le retrait avec/sans réservation et l'exposition sur la fiche.

## Commission plateforme (F8.4)

Une réservation de nuitée fige la **commission Kaikun** dans
`bookings.commission_xof`, via
[`CommissionCalculator`](../../Support/Billing/CommissionCalculator.php) — le
même calcul et le **même taux paramétrable** (`commission.default_rate`,
Paramètres → Réglages) que la mobilité, le tourisme et les missions prestataires.

⚠️ **Ce n'était pas le cas avant F8.4** : la colonne restait à `0` sur toutes les
nuitées. L'export comptable et le tableau de bord sous-estimaient donc le revenu
réel de la plateforme sur cet univers.

⚠️ **La caution n'entre pas dans l'assiette** : c'est un dépôt rendu au client,
pas un revenu. Seul `nuits × prix` est commissionné.

⚠️ La commission est **figée à la réservation**, jamais recalculée : changer le
taux au back-office ne réécrit pas l'historique comptable des réservations déjà
prises.
