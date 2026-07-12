# `features/` — Fonctionnalités (chargées à la demande)

Chaque fonctionnalité est un dossier autonome avec ses composants et ses routes,
**chargé en lazy loading** via `loadComponent` / `loadChildren` depuis
`app.routes.ts`. Exemples à venir : `auth/`, `public/` (pages publiques &
catalogues), `account/`, `back-office/`.

Une fonctionnalité peut consommer le [`../core`](../core) (services, guards) et le
[`../shared`](../shared) (composants d'UI), mais **ne dépend pas** d'une autre
fonctionnalité.
