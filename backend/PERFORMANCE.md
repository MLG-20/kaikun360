# Durcissement & performance — Kaikun 360 (Phase B17)

Ce document trace les mesures de durcissement et de performance appliquées au
backend lors de la phase **B17**. Il complète `app/Support/README.md` (contrat
d'API, cache) et `CONFIDENTIALITE.md` (RGPD).

## B17.1 — Index de base de données

Index B-tree ajoutés sur les colonnes **réellement filtrées ou triées** par les
endpoints de catalogue/recherche et qui n'étaient pas déjà couvertes (index
explicite, clé étrangère `constrained()`, ou index composite) :

| Table | Colonnes indexées (ajout B17.1) |
| --- | --- |
| `stays` | `price_per_night_xof` |
| `vehicles` | `price_per_day_xof` |
| `mobility_services` | `departure`, `destination` |
| `tourism_experiences` | `destination`, `price_xof` |
| `payments` | `status` |

Déjà couvert en amont : `properties` (status, type, verification_level,
tourist_zone, price_xof, composite (status, type), FK géo), `bookings` (status,
user_id, composite morph + dates), `vehicles`/`mobility_services`/
`tourism_experiences` (type, status, provider_id, departure_at).

Volontairement non indexé (bénéfice quasi nul) : colonnes très basse cardinalité
(`capacity`, `has_driver`) et recherches plein texte `LIKE '%…%'` (un index
B-tree n'aide pas un joker en tête).

Migration : `2026_07_12_180000_add_catalog_search_indexes`. Garde-fou :
`tests/Feature/Performance/CatalogIndexTest`.

## B17.2 — Cache des catalogues

Voir `app/Support/README.md` §6. En résumé : `App\Support\Cache\CatalogCache`
met en cache le résultat rendu des 5 index publics, avec invalidation par
versioning déclenchée automatiquement sur les événements `saved`/`deleted` des
modèles de catalogue.

## B17.3 — Revue des Form Requests & chasse aux N+1

### Revue des entrées (aucune donnée non validée n'atteint le métier)

Conclusion de la revue : **toute écriture valide son entrée** avant d'atteindre
la couche métier, soit via un Form Request dédié, soit via `$request->validate()`
inline.

- Les créations/mises à jour de ressources (`store`/`update`) passent par des
  Form Requests dédiés (`StorePropertyRequest`, `UpdateUserRequest`,
  `StoreQuoteRequest`, `SanctionProviderRequest`, …). Aucun `::create()`,
  `->update()`, `->fill()` n'est alimenté par `$request->input()/all()/only()`.
- Les actions de décision qui acceptent un corps optionnel le valident :
  rejets (`reason` `nullable|string|max:500`), remboursement (`amount_xof`
  `nullable|integer|min:1`), envoi de code (`channel` `in:email,phone`).
- Les actions sans corps (validations, favoris, annulations, anonymisation RGPD)
  agissent sur la ressource liée / l'utilisateur authentifié : rien à valider.
- Les paramètres de **filtre** des listes (GET) sont type-coercés
  (`integer()`/`string()`), bornés (`per_page` plafonné à 100) et injectés en
  bindings paramétrés : aucune donnée brute ne transite en requête.

### Chasse aux N+1

`PropertyResource` accède aux relations `region/department/commune/owner` sans
`whenLoaded`. Tous ses sites d'appel doivent donc charger ces relations. Audit
complet des points d'appel :

- Déjà corrects : catalogues (`with([...])`), favoris, `mine`/`store`/`update`
  de gestion des biens, validations, `PropertyValidator`, `AdminCatalog`,
  `StayResource` (`property.region/…`).
- **Corrigé (N+1 réel)** : `MandateResource` embarque `PropertyResource`, mais
  les listes de mandats ne chargeaient que `property` (pas ses sous-relations).
  Correctif : `with(['property.region', 'property.department',
  'property.commune', 'property.owner'])` dans `ManageController::mine`/`show`
  et `AdminDossierController::mandates`.

Les autres Resources imbriquées (UserResource→profile, ProviderResource→
certifications, ConstructionRequestResource→milestones, TeamBuilding→quotes,
ReviewResource→author) protègent leurs relations par `whenLoaded` : elles sont
simplement omises si non chargées, sans N+1.

Garde-fou : `tests/Feature/Performance/MandateEagerLoadingTest` vérifie que le
nombre de requêtes de la liste des mandats reste borné et indépendant du nombre
de lignes.

## B17.4 — Tests de charge

Approche pragmatique, sans infrastructure externe : une commande artisan rejoue
N requêtes réelles à travers le kernel HTTP complet (middlewares, sérialisation
Resource, cache) sur un endpoint de catalogue, sous un volume amorcé, et compare
le régime « à froid » (cache vidé avant chaque appel) au régime « à chaud »
(cache actif).

```bash
php artisan catalog:benchmark --rows=500 --requests=100
# --endpoint=/api/v1/properties par défaut
```

Le jeu de données est amorcé dans une transaction annulée en fin de test : la
base n'est pas polluée (à lancer de préférence sur une base de dev).

Résultat typique (200 biens, machine de dev) :

| Régime | Latence moy. | Requêtes SQL / appel |
| --- | --- | --- |
| À froid (cache vidé) | ~16 ms | 3 |
| À chaud (cache actif) | ~1,5 ms | 0 |

→ Le cache sert le catalogue **~10× plus vite et sans toucher la base**. À froid,
le coût SQL est **constant** (index B17.1 + eager loading), indépendant du volume.

Pour une montée en charge concurrente (si `ab`/`wrk` sont installés), viser un
serveur lancé (`php artisan serve`) :

```bash
ab -n 1000 -c 50 http://127.0.0.1:8000/api/v1/properties
```

Garde-fou CI : `tests/Feature/Performance/CatalogLoadTest` vérifie que le nombre
de requêtes SQL du catalogue est **indépendant du volume** (à froid) et **nul à
chaud** (servi du cache).
