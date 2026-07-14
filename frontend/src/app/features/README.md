# `features/` — Les grandes fonctionnalités du site

> **En une phrase :** ce dossier contient les **écrans que voit l'utilisateur**,
> regroupés par grande fonctionnalité (se connecter, l'accueil, et plus tard les
> catalogues, l'espace personnel, le back-office…).

---

## 1. Expliqué simplement

Une **fonctionnalité** = un ensemble de pages qui vont ensemble et qui rendent un
service précis. Par exemple « tout ce qui concerne la connexion » est une
fonctionnalité, « l'accueil » en est une autre.

Chaque fonctionnalité n'est **téléchargée par le navigateur que lorsqu'on en a
besoin** (on parle de « chargement à la demande »). Résultat : le site s'ouvre
plus vite, car on ne charge pas tout d'un coup.

### Les « cadres » autour des pages (layouts)

Une page ne s'affiche jamais toute seule : elle est posée dans un **cadre** qui
fournit le décor commun. Il y en a deux :

- **Cadre principal du site** ([`../layouts/main-layout`](../layouts/main-layout)) —
  l'en-tête (logo + menu) en haut, le pied de page en bas, et la page au milieu.
  C'est le cadre de l'accueil et, plus tard, des catalogues.
- **Cadre d'authentification** ([`auth/auth-layout`](auth/auth-layout)) — l'écran
  scindé « signature de marque + formulaire », **sans le grand menu**, pour que la
  personne se concentre sur sa connexion.

La toute première brique de l'application (`App`) ne fait qu'une chose : afficher
« la bonne page dans le bon cadre » selon l'adresse visitée.

### Ce qui existe aujourd'hui

- **`home/`** — la **page d'accueil complète** (F2.2) : accroche + moteur de
  recherche, grille des 9 univers, protocole de confiance, **vitrine de biens
  vérifiés connectée aux données réelles**, bandeau diaspora, services, teaser
  du simulateur, statistiques et appel à l'action final. 👉 README détaillé :
  [`home/README.md`](home/README.md).
- **`auth/`** — **créer un compte et se connecter** (connexion, inscription,
  vérification, mot de passe oublié). 👉 Voir le README détaillé :
  [`auth/README.md`](auth/README.md).
- **`catalog/`** — la **page de résultats de recherche** (`/recherche`, F2.1) :
  moteur de recherche + catalogue générique piloté par l'URL.
- **`immo/`** — l'**univers Immobilier** (F2.3) : la page vitrine `/immobilier`
  (bandeau de confiance + catalogue filtrable) et la **fiche d'un bien**
  `/immobilier/:id` (description, localisation, vérification, **formulaire de
  demande de visite**).
- **`stay/`** — l'**univers Nuitées** (F2.3) : la page vitrine `/nuitees` et la
  **fiche d'une nuitée** `/nuitees/:id` (équipements, règlement, **calendrier de
  disponibilité**, avis clients, demande de réservation).

À venir : les autres univers (Tourisme, Transport…), l'espace personnel, le
back-office.

---

## 2. Détails techniques

- Chaque fonctionnalité est un dossier autonome (ses composants + ses routes),
  **chargé en lazy loading** via `loadComponent` / `loadChildren` depuis
  [`../app.routes.ts`](../app.routes.ts).
- Une fonctionnalité peut consommer le [`../core`](../core) (services, guards,
  intercepteurs) et le [`../shared`](../shared) (composants d'interface
  réutilisables), mais **ne dépend jamais d'une autre fonctionnalité**.
- Les layouts sont des **composants de route** : `App` est réduit à un
  `<router-outlet>`, et chaque branche de route choisit son layout.
- Styles partagés des pages d'authentification :
  [`../../styles/_auth.scss`](../../styles/_auth.scss) (globaux car réutilisés par
  plusieurs pages ; l'encapsulation Angular empêcherait le partage sinon).
- Styles partagés des **pages d'univers** (bandeau `.uni-hero`, ossature de fiche
  `.uni-detail-*`) : [`../../styles/_universe.scss`](../../styles/_universe.scss),
  réutilisés par `immo/` et `stay/` (F2.3).
- Les **fiches détaillées** consomment `CatalogService.property/stay/stayAvailability`,
  `ReviewService` (avis) et `RequestService` (dépôt de demande, auth requise).
  Les cartes de catalogue mènent à la fiche via l'`input [link]` de
  `app-listing-card` (lien étiré).
