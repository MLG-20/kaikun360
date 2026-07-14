# `core/` — Socle applicatif (singletons)

Services et briques **transverses instanciés une seule fois** pour toute
l'application (approche standalone, sans NgModule) :

- **services/** — `AuthService` (session, token en mémoire), services d'API.
- **api/** — accès HTTP typés au backend `/api/v1` :
  - `CatalogService` — catalogues publics (index paginés) **et** détail
    (`property`, `stay`, `stayAvailability` — F2.1/F2.3 ; `experience`,
    `experienceAvailability`, `vehicle` — F2.4).
  - `RequestService` — dépôt de demandes contextuelles `POST /requests`
    (demande de visite/réservation ; **auth requise**) — F2.3.
  - `ReviewService` — avis publiés `GET /reviews` d'une entité notée — F2.3.
- **interceptors/** — `tokenInterceptor` (ajoute le Bearer), `errorInterceptor`
  (gestion centralisée : 401 → login, 422 → erreurs de formulaire, 500 → page
  d'erreur).
- **guards/** — `authGuard`, `roleGuard` (protection des routes).

Fourni via `app.config.ts` (`provideHttpClient(withInterceptors(...))`, etc.).
Ne contient **aucun composant d'interface** (voir [`../shared`](../shared)).
