# Module Immo — Achat / vente / location mensuelle

Gère les **biens immobiliers** : dépôt par un propriétaire, validation par un
agent, puis publication au catalogue public.

---

## Couche de données (phase B2.1)

### Tables

| Table | Rôle | Points clés |
|---|---|---|
| `properties` | Biens immobiliers | `owner_id`, `type`, `title`, `price_xof`, géo (`region_id`, `department_id`, `commune_id`, `tourist_zone`), `status`, `verification_level` |
| `property_documents` | Pièces du bien (titre foncier, bail…) | fichier sur disque privé, `validation_status` |

Index posés pour les filtres de catalogue fréquents : `status`, `type`, `price_xof`
(+ index composé `status+type` ; `region/department/commune` indexés via les FK).

### Modèles (`app/Modules/Immo/Models/`)

- `Property` — `belongsTo` owner (User), région, département, commune ;
  `hasMany` documents. Scope **`published()`** : centralise la règle « le catalogue
  public ne montre que les biens validés » (`status = publie`).
- `PropertyDocument` — `belongsTo` property.

### Enums (`app/Modules/Immo/Enums/`)

- `PropertyType` : appartement, maison, villa, studio, terrain, bureau, commerce, autre.
- `PropertyStatus` : `en_attente_validation` (défaut), `publie`, `suspendu`,
  `archive`, `rejete`.

> **Statut par défaut = `en_attente_validation`** : un bien neuf n'est JAMAIS
> visible publiquement avant validation par un agent (exigences B2 / B15).

---

## Référentiel géographique du Sénégal (partagé)

La localisation s'appuie sur un **référentiel structuré** (pas de texte libre),
réutilisable par les autres modules (Stay, Mobility…). Modèles dans `app/Models/`
(données de référence transverses) : `Region`, `Department`, `Commune`.

| Niveau | Table | Volume | Source |
|---|---|---|---|
| Région | `regions` | **14** | `SenegalGeographySeeder` |
| Département | `departments` | **46** | `SenegalGeographySeeder` |
| Commune | `communes` | ~557 (cible) | `CommunesSeeder` ← `database/data/communes.json` |

> ⚠️ Le fichier `database/data/communes.json` est **amorcé** (région de Dakar +
> Guédiawaye détaillées, chefs-lieux des 46 départements = 68 communes). Il doit
> être **complété avec la liste officielle ANSD** (~557 communes). L'import se fait
> sans toucher au code : on enrichit le JSON puis `php artisan db:seed --class=CommunesSeeder`.
>
> **F5.7 — en attendant, la liste s'enrichit d'elle-même.** Le formulaire de bien
> (`owner-property-form-page`) laisse le propriétaire **proposer** une commune
> absente du select (`POST /api/v1/communes`, `GeoController::storeCommune`,
> tout utilisateur `auth:sanctum`) : réutilise `StoreCommuneRequest`
> (module Admin) telle quelle, **aucune modération** — la commune rejoint
> directement le référentiel partagé, visible aussitôt pour tout autre
> propriétaire du même département. C'est une donnée géographique factuelle, pas
> un contenu public à valider avant diffusion ; une commune mal orthographiée
> reste corrigeable depuis l'écran Référentiels du back-office (`AdminGeoController`,
> CRUD déjà en place).

Hiérarchie : `Region 1—N Department 1—N Commune`. Un bien pointe vers les trois
niveaux (`region_id`, `department_id`, `commune_id`).

> 📷 **Photos du bien (F4.3)** — relation `Property::media()` (morphMany vers la
> table polymorphe `media`, B12.1), triée **couverture d'abord** (`is_primary`)
> puis `position`, et restreinte aux médias `visible()` (un média masqué en
> modération disparaît des annonces sans être supprimé).
>
> `PropertyResource` en expose deux clés, **dès que la relation est chargée** :
> - `photos` — la galerie complète (`MediaResource`), consommée par la fiche
>   publique et la fiche du propriétaire ;
> - `photo_url` — raccourci vers la couverture, consommé par les **cartes du
>   catalogue** ; `null` si le bien n'a aucune photo (le front affiche alors sa
>   vignette de repli plutôt qu'une image cassée).
>
> ⚠️ Les contrôleurs doivent **charger `media`** (`->with([... , 'media'])`) :
> `PropertyCatalogController` (index/show/compare), `PropertyManagementController`
> et `StayCatalogController` (`property.media`, une nuitée s'illustrant avec les
> photos de son bien) le font. Sans eager load, la clé est simplement absente —
> jamais de N+1.
>
> Le dépôt/retrait passe par les endpoints transversaux `POST /media/upload` et
> `DELETE /media/{media}` ; le choix de la couverture par
> `PATCH /media/{media}/primary`. Tous sont autorisés via la **`PropertyPolicy`**
> (`update`) : seul le propriétaire du bien — ou un admin — illustre son bien.

---

## Catalogue public (phase B2.2)

| Méthode | URL | Accès |
|---|---|---|
| GET | `/api/v1/properties` | public — liste filtrable & paginée |
| GET | `/api/v1/properties/{id}` | public — détail d'un bien publié |

> 🔒 **Garantie** : ces endpoints n'exposent QUE les biens publiés
> (`Property::published()`). Un bien en attente/suspendu/rejeté n'apparaît jamais
> dans la liste et renvoie **404** au détail.

### Filtres (query string)

`region_id`, `department_id`, `commune_id`, `tourist_zone`, `type`,
`price_min`, `price_max`, `verification_level`, `q` (recherche titre),
`sort` (`recent` | `price_asc` | `price_desc`), `per_page` (1–50).

Tous validés (`exists` pour les FK, `Rule::in` pour type/sort). Exemple :
`/api/v1/properties?region_id=1&type=villa&price_max=80000000&sort=price_asc`

### Réponse

- Liste : enveloppe paginée native `{ data: [...], links: {...}, meta: {...} }`
  (via `PropertyResource::collection`).
- Détail : `{ data: {...} }`.
- `PropertyResource` n'expose du propriétaire que `id` + `name`, et restitue la
  localisation par les **noms** région/département/commune (référentiel).

> Le détail d'un bien NON publié par son **propriétaire** apparaît dans la
> gestion privée ci-dessous (B2.3).

---

## Gestion privée par le propriétaire (phase B2.3)

| Méthode | URL | Accès |
|---|---|---|
| GET | `/api/v1/properties/mine` | `auth:sanctum` — mes biens (tous statuts) |
| GET | `/api/v1/properties/mine/{property}` | `auth:sanctum` — fiche d'un de mes biens (tous statuts, 404 si bien d'autrui) |
| POST | `/api/v1/properties` | `auth:sanctum` — déposer un bien (rôle proprietaire/admin) |
| PATCH | `/api/v1/properties/{property}` | `auth:sanctum` — modifier (propriétaire/admin) |
| GET | `/api/v1/properties/{property}/documents` | `auth:sanctum` — lister les pièces d'un bien (F4.5) |
| POST | `/api/v1/properties/{property}/documents` | `auth:sanctum` — ajouter une pièce |
| DELETE | `/api/v1/properties/{property}/documents/{document}` | `auth:sanctum` — retirer une pièce (F4.5) |
| GET | `/api/v1/properties/{property}/documents/{document}/download` | **URL signée** |

### Règles

- **`PropertyPolicy`** (`create`/`view`/`update`/`manageDocuments`) : un
  propriétaire ne gère que **ses** biens ; l'admin gère tout ; super_admin bypass.
  Enregistrée dans `AppServiceProvider`.
- Un bien déposé démarre **toujours** en `en_attente_validation` (jamais publié
  directement). Le statut n'est pas modifiable via `PATCH` (réservé à la
  validation, phase B2.4).
- **Cohérence géographique** validée (`StorePropertyRequest`/`UpdatePropertyRequest`) :
  le département doit appartenir à la région, la commune au département.
- Documents : disque privé, formats PDF/JPG/PNG ≤ 5 Mo, accès par **URL signée** (10 min).
  La **liste** (`listDocuments`) et la **suppression** (`deleteDocument`, retire la
  ligne **et** le fichier) sont réservées au propriétaire via `manageDocuments`
  (403 sinon) — alimentent l'écran **« Documents »** de l'espace propriétaire (F4.5).
  Le compteur `documents_count` est exposé par `PropertyResource` quand la liste
  `mine` le demande (`withCount('documents')`), pour le badge « N documents ».

### Briques

- Form Requests : `StorePropertyRequest`, `UpdatePropertyRequest`, `StorePropertyDocumentRequest`.
- Resources : `PropertyResource`, `PropertyDocumentResource`.
- Contrôleur : `PropertyManagementController`.

### Caution pour une location au mois (F5.8)

`properties.caution_xof` (montant MENSUEL, FCFA) et `properties.caution_months`
(nombre de mois) sont **deux champs indépendants, librement déclarés** par le
propriétaire — pas un multiple imposé du loyer (`price_xof`), à sa discrétion
(1, 2, 3 mois…). Le TOTAL (`caution_xof × caution_months`) est calculé par
l'accesseur `Property::getCautionTotalXofAttribute()` (pas de colonne dédiée,
pour ne jamais désynchroniser le total des deux valeurs sources) et exposé par
`PropertyResource` sous `caution_total_xof`.

`price_xof` (le loyer) devient **obligatoire côté frontend** dès que le mode de
location inclut le mois (`OwnerPropertyFormPageComponent::applyMode()` bascule
le validateur `required` en même temps qu'il (dés)active le bloc nuitées) —
inchangé côté backend (`nullable`, un bien loué seulement à la nuitée n'a pas de
loyer mensuel).

⚠️ **Aucun suivi de statut** (retenue/restituée/perdue) contrairement à la
caution des nuitées (`stays.caution_xof`, module Stay) : il n'existe aujourd'hui
aucune notion de bail/locataire à laquelle rattacher un tel suivi pour une
location au mois — voir aussi la décision produit de retirer la caution des
nuitées et des véhicules ci-dessous.

**La caution est désormais réservée à la gestion locative.** Décision produit
(2026-08-24) : `stays.caution_xof` (module Stay) et `vehicles.caution_xof`
(module Mobility) sont vidées (migrations
`clear_caution_xof_on_stays_table` / `clear_caution_xof_on_vehicles_table`,
sans toucher `bookings.caution_xof`/`caution_status` — le sort d'une caution déjà
collectée sur une réservation en cours reste à trancher côté back-office) et
leurs factories ne génèrent plus de valeur aléatoire. Les trois affichages
publics correspondants (fiche nuitée, fiche véhicule — caractéristiques, encart
tarif, devis de réservation) ont été retirés.

---

## Événements & validation (phase B2.4)

| Méthode | URL | Accès |
|---|---|---|
| PATCH | `/api/v1/properties/{property}/approve` | `auth:sanctum` + `can:valider:bien` |
| PATCH | `/api/v1/properties/{property}/reject` | `auth:sanctum` + `can:valider:bien` |

### Flux

1. **Dépôt** → `PropertyCreated` est émis → listener `NotifyAgentsOfNewProperty`
   notifie tous les utilisateurs ayant la permission `valider:bien`
   (agents, admins, super_admin).
2. **Validation** (`approve`) → statut `publie`, `published_at`/`approved_by`/`approved_at`
   renseignés, **audit**, puis `PropertyValidated` est émis → listener
   `NotifyOwnerOfPropertyValidated` informe le propriétaire.
3. **Rejet** (`reject`) → statut `rejete` (+ motif optionnel, audité).

### Détails techniques

- Permission `valider:bien` appliquée par le middleware `can:valider:bien`.
- Listeners enregistrés explicitement dans `AppServiceProvider::configureEvents()`
  (les modules ne sont pas couverts par l'auto-découverte d'événements).
- Notifications par mail (loggées en dev). **Push/WhatsApp + envoi asynchrone
  (jobs)** à brancher en **phase B16**.

---

## Comparaison (phase B2.5)

| Méthode | URL | Accès |
|---|---|---|
| GET | `/api/v1/properties/compare?ids=1,2,3` | public — comparer (max 4) |

- **Comparaison** : renvoie les biens **publiés** parmi les `ids` fournis,
  **4 maximum** pour rester lisible.
- ⚠️ **Ce filtrage est SILENCIEUX** — un identifiant inconnu, supprimé ou
  dépublié est ignoré, et au-delà de quatre le reste est tronqué : **jamais
  d'erreur**, une liste simplement plus courte que la demande. Depuis F8.15.e
  c'est un **contrat** avec l'écran `/immobilier/comparer` (frontend), qui
  reproduit le plafond pour refuser le cinquième bien *avec un message* et
  compare la réponse à sa demande pour signaler les biens disparus. Abaisser ce
  plafond, ou transformer ces cas en 422, casserait cet écran : deux tests de
  `FavoriteAndCompareTest` le verrouillent.

> **Favoris** : autrefois limités aux biens immobiliers ici, ils sont désormais
> **polymorphes (tous univers)** et vivent dans la **couche transversale** —
> endpoints `/api/v1/favorites` (voir `routes/transversal.php`,
> `App\Http\Controllers\FavoriteController`, `App\Support\Favoritables`). La
> table `favorites` porte maintenant `favoritable_type` / `favoritable_id`.
