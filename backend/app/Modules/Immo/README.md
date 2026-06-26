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
