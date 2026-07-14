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
- **`explore/`** — l'**univers Tourisme** (F2.4) : la page vitrine `/tourisme`
  et la **fiche d'une expérience** `/tourisme/:id` (programme, inclusions,
  **places restantes**, avis, demande de réservation).
- **`mobility/`** — les **univers Transport & Mobilité** (F2.4) : la vitrine
  `/transport` + la **fiche d'un véhicule** `/transport/:id` (caractéristiques,
  chauffeur, caution, avis, demande) ; et la vitrine `/mobilite` (navettes/
  transferts — **vitrine seule**, pas de fiche côté backend).
- **`build/`** — l'**univers Construction** (F2.5) : la page de conversion
  `/construction` avec un **simulateur de budget interactif** (objectif, surface,
  finition → estimation FCFA en direct) et un formulaire de devis pré-rempli.
- **`manage/`** — l'**univers Gestion locative** (F2.5) : la page de conversion
  `/gestion-locative` (promesse, étapes, bénéfices, mise en relation).
- **`diaspora/`** — l'**univers Diaspora** (F2.5) : la page de conversion
  `/diaspora` (protocole de confiance anti-arnaque, référent unique, contact).
- **`team-building/`** — l'**univers Team building** (F2.5) : la page de
  conversion `/team-building` (formules, étapes, demande de devis).
- **`pro/`** — **Kaikun Pro** (F2.5) : la page de recrutement des prestataires
  `/pro` (atouts, audiences, certification) ; CTA = inscription.

Ces cinq pages de conversion partagent le formulaire réutilisable
[`app-lead-form`](../shared/components/lead-form) (dépôt de demande auth-gated).

À venir : les composants transverses (fiche générique, galerie, avis, WhatsApp),
les formulaires intelligents (dépôt de bien, devis), l'espace personnel, le
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
  `.uni-detail-*`, chips/avis/disponibilité `.uni-chips`/`.uni-review`/`.uni-avail`) :
  [`../../styles/_universe.scss`](../../styles/_universe.scss), réutilisés par
  `immo/`, `stay/`, `explore/` et `mobility/` (F2.3 → F2.4).
- Styles partagés des **pages de conversion** (étapes numérotées `.conv-steps`,
  grilles d'atouts `.conv-features`, bandeau d'appel à l'action `.conv-cta`) :
  [`../../styles/_conversion.scss`](../../styles/_conversion.scss), réutilisés par
  `build/`, `manage/`, `diaspora/`, `team-building/` et `pro/` (F2.5). Le
  simulateur de construction garde ses styles propres (`build-sim-*`).
- Les **fiches détaillées** consomment `CatalogService`
  (`property`/`stay`/`stayAvailability`, `experience`/`experienceAvailability`,
  `vehicle`), `ReviewService` (avis stay/vehicle/experience) et `RequestService`
  (dépôt de demande, auth requise). Les cartes de catalogue mènent à la fiche via
  l'`input [link]` de `app-listing-card` (lien étiré) ; la mobilité reste non
  cliquable (pas de fiche backend).
