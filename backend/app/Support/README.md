# Socle API — Kaikun 360 (Phase B0.4)

Ce dossier `app/Support/` regroupe les briques transverses de l'API. Ce README
documente le **contrat d'API** mis en place : tout endpoint du projet doit le respecter.

---

## 1. Versionnement de l'API

Toute l'API est servie sous le préfixe **`/api/v1`** (défini dans `bootstrap/app.php`,
paramètre `apiPrefix`). Une future version majeure incompatible serait `/api/v2`,
sans casser les clients existants.

Les routes ne sont pas écrites en vrac : `routes/api.php` se contente de **charger
automatiquement** les routes de chaque module (`app/Modules/<Module>/routes/api.php`).

Exemple : `app/Modules/Core/routes/api.php` déclare `GET /version`
→ accessible à l'URL **`/api/v1/version`**.

---

## 2. Enveloppe de réponse standard (succès)

Toutes les réponses de succès passent par la classe `ApiResponse` (`app/Support/ApiResponse.php`).

| Cas | Méthode | Format renvoyé |
|---|---|---|
| Succès simple | `ApiResponse::success($data)` | `{ "data": ... }` |
| Succès + métadonnées | `ApiResponse::success($data, $meta)` | `{ "data": ..., "meta": {...} }` |
| Ressource créée (201) | `ApiResponse::created($data)` | `{ "data": ... }` (HTTP 201) |
| Suppression (204) | `ApiResponse::noContent()` | *(corps vide, HTTP 204)* |
| Liste paginée | `ApiResponse::paginated($paginator)` | `{ "data": [...], "meta": {...}, "links": {...} }` |

> **Règle :** le contenu utile est **toujours** sous la clé `data`. Le frontend
> Angular peut donc lire `response.data` partout, sans cas particulier.

---

## 3. Format d'erreur standard

Les erreurs sont gérées **globalement** dans `bootstrap/app.php` (`withExceptions`).
Sur toute route `/api/*`, la réponse est garantie en JSON, au format :

```json
{ "message": "Message lisible", "errors": { "champ": ["détail"] } }
```

La clé `errors` n'apparaît que lorsqu'il y a un détail par champ (validation).

| Code HTTP | Quand | Corps |
|---|---|---|
| **422** | Validation échouée (Form Request) | `{ "message", "errors": { champ: [...] } }` *(géré par Laravel)* |
| **401** | Non authentifié (token absent/invalide) | `{ "message": "Non authentifié." }` |
| **403** | Action refusée par une policy | `{ "message": "Action non autorisée." }` |
| **404** | Ressource ou route introuvable | `{ "message": "Ressource introuvable." }` |

Pour une erreur ponctuelle gérée à la main dans un contrôleur :
`return ApiResponse::error('Message', 409, $erreursOptionnelles);`

---

## 4. CORS

Configuré dans `config/cors.php`. Seules les origines listées dans la variable
d'environnement `CORS_ALLOWED_ORIGINS` (séparées par des virgules) peuvent appeler
l'API depuis un navigateur. En dev : `http://localhost:4200` (serveur Angular).
En prod : les domaines Kaikun officiels. **Jamais de domaine codé en dur.**

---

## 5. Limitation de débit (rate limiting)

Le limiteur nommé **`api`** est défini dans `app/Providers/AppServiceProvider.php` :
**60 requêtes / minute**, comptées par utilisateur connecté (id) ou par IP si anonyme.
Il est appliqué à toute l'API via `bootstrap/app.php` (`throttle:api`).

Chaque réponse renvoie les en-têtes `X-RateLimit-Limit` et `X-RateLimit-Remaining`.
Au-delà de la limite : **HTTP 429** (Too Many Requests).
