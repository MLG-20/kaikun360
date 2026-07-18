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

Hiérarchie : `Region 1—N Department 1—N Commune`. Un bien pointe vers les trois
niveaux (`region_id`, `department_id`, `commune_id`).

> 📷 La relation **médias** (galerie photo) sera ajoutée en **phase B12**
> (table polymorphe `media`).

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
| POST | `/api/v1/properties` | `auth:sanctum` — déposer un bien (rôle proprietaire/admin) |
| PATCH | `/api/v1/properties/{property}` | `auth:sanctum` — modifier (propriétaire/admin) |
| POST | `/api/v1/properties/{property}/documents` | `auth:sanctum` — ajouter une pièce |
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

### Briques

- Form Requests : `StorePropertyRequest`, `UpdatePropertyRequest`, `StorePropertyDocumentRequest`.
- Resources : `PropertyResource`, `PropertyDocumentResource`.
- Contrôleur : `PropertyManagementController`.

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

> **Favoris** : autrefois limités aux biens immobiliers ici, ils sont désormais
> **polymorphes (tous univers)** et vivent dans la **couche transversale** —
> endpoints `/api/v1/favorites` (voir `routes/transversal.php`,
> `App\Http\Controllers\FavoriteController`, `App\Support\Favoritables`). La
> table `favorites` porte maintenant `favoritable_type` / `favoritable_id`.
