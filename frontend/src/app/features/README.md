# `features/` — Fonctionnalités (chargées à la demande)

Chaque fonctionnalité est un dossier autonome avec ses composants et ses routes,
**chargé en lazy loading** via `loadComponent` / `loadChildren` depuis
`app.routes.ts`. Exemples à venir : `public/` (pages publiques & catalogues),
`account/`, `back-office/`.

Une fonctionnalité peut consommer le [`../core`](../core) (services, guards) et le
[`../shared`](../shared) (composants d'UI), mais **ne dépend pas** d'une autre
fonctionnalité.

## Layouts (route-level)

Les pages sont rendues dans un **layout** (composant de route), jamais dans la
racine `App` (réduite à un `<router-outlet>`) :

- [`../layouts/main-layout`](../layouts/main-layout) — site public : en-tête
  global + contenu routé + pied de page.
- [`auth/auth-layout`](auth/auth-layout) — authentification : écran scindé
  (signature de marque + carte de formulaire), sans le méga-header.

## `home/` (F1.1)

Page d'accueil provisoire (vitrine du design system : hero orbital, cartes,
galerie, données de démonstration). Rendue dans le layout principal à `''`.
Sera remplacée par la vraie page d'accueil branchée sur l'API en F2+.

## `auth/` (F1)

Authentification & onboarding, rendue dans `auth-layout`. Routes déclarées dans
[`auth/auth.routes.ts`](auth/auth.routes.ts) (`loadChildren` depuis la racine).

| Page | Route | Statut | Endpoint |
| --- | --- | --- | --- |
| Connexion | `/auth/connexion` | ✅ F1.1 | `POST /auth/login` |
| Inscription + onboarding | `/auth/inscription` | ✅ F1.2 | `POST /auth/register` |
| Vérification (code) | `/auth/verification` | 🔜 F1.3 | `/auth/verify`, `/auth/verify/send` |
| Mot de passe oublié / réinitialisation | `/auth/mot-de-passe-oublie` | 🔜 F1.3 | `/auth/password/forgot`, `/auth/password/reset` |
| Bouton « Connexion Google » | (sur `/auth/connexion`) | 🔜 F1.4 | `POST /auth/google` |

La session (jeton en mémoire) et `hasRole` sont fournis par
[`../core/auth/auth.service`](../core/auth/auth.service.ts) ; la redirection
post-connexion suit le `?redirect=` posé par l'`authGuard`.

Les styles de mise en page communs aux pages auth (`.auth-head`, `.auth-form`,
`.auth-link`, `.auth-row`…) sont **globaux** dans `src/styles/_auth.scss` (une
classe scopée à un composant ne serait pas partageable entre pages) ; chaque page
ne garde en local que son style spécifique (ex. le sélecteur de profil).
