# `features/account/` — L'espace client (F3)

> **En une phrase :** l'**espace personnel** de la personne connectée, accessible
> sous `/mon-espace`, où elle retrouve ses demandes, réservations, favoris,
> documents et messages.

---

## 1. Expliqué simplement

Quand quelqu'un se connecte, il arrive dans **son** espace : une page qui lui est
propre, avec sur le côté un **menu** listant tout ce qu'il peut y faire, et au
milieu le contenu de la rubrique choisie.

Cet espace est **privé** : on ne peut y entrer que connecté. Si une personne non
connectée tente d'ouvrir une adresse en `/mon-espace…`, elle est **renvoyée vers
la page de connexion**, puis ramenée là où elle voulait aller une fois connectée.

On construit cet espace **rubrique par rubrique**. Aujourd'hui, le **cadre**, la
**page d'accueil** et la rubrique **Profil** sont en place ; les rubriques
marquées « Bientôt » s'activeront aux étapes suivantes.

### Ce qui existe aujourd'hui

- **Le cadre (F3.1)** — [`../../layouts/account-layout`](../../layouts/account-layout) :
  un **en-tête dédié épuré** (`account-header/`, voir plus bas) et une **barre
  latérale** avec les liens des rubriques. **Ni méga-menus ni pied de page** du
  site public (choix UX : l'espace doit se sentir « chez soi »).
- **L'en-tête dédié (F3.1)** — [`account-header/`](account-header) : marque
  (retour à l'accueil), lien discret **« Retour au site »**, et **menu
  utilisateur** (avatar + nom → **Mon profil** [F3.2] et **Se déconnecter**).
  Volontairement **sans les méga-menus marketing** du site public : dans son
  espace, la personne doit se sentir chez elle.
- **La page d'accueil (F3.1)** — [`overview/`](overview) : salutation, **rappel de
  vérification** du compte si besoin, et des **tuiles** vers chaque rubrique.
- **La rubrique Profil (F3.2)** — [`profile/`](profile) : **identité** (nom + ville
  modifiables ; e-mail, téléphone, statut, type et date d'inscription en lecture
  seule), **pièces justificatives** (liste + dépôt PDF/JPG/PNG ≤ 5 Mo, avec
  téléchargement par URL signée), et **suppression du compte** (anonymisation
  RGPD, derrière confirmation).

Les rubriques Mes demandes, Réservations, Favoris, Notifications et Messages
arrivent en F3.3 → F3.7.

---

## 2. Expliqué techniquement

### Structure

- **`account-nav.ts`** — **source unique** de la navigation (`ACCOUNT_NAV`) :
  chaque section a `{ label, description, path, icon, ready }`. Le drapeau
  `ready` distingue une section **construite** (vrai lien) d'une section **à
  venir** (affichée « Bientôt », non cliquable → **aucun lien mort**). On
  bascule `ready` à `true` au fil des sous-phases.
- **`account-icon.ts`** — petit composant `app-account-icon` rendant une icône
  SVG à partir d'une clé `AccountIcon` (`currentColor`, sans dépendance).
  Mutualisé par la barre latérale et les tuiles.
- **`account-header/`** — `AccountHeaderComponent` (`app-account-header`) :
  en-tête dédié de l'espace. Injecte `AuthService` (identité + `logout()`) ;
  menu utilisateur déroulant (signal `menuOpen`, fermé à la navigation / Échap /
  clic extérieur, même mécanique que le header public). Utilisé par le layout à
  la place de `app-header`.
- **`account.routes.ts`** — `ACCOUNT_ROUTES` : le layout `AccountLayoutComponent`
  porte `canActivate: [authGuard]` ; les sections sont ses routes enfants
  (chargées à la demande). L'accueil est la route enfant `''`.
- **`overview/`** — `AccountOverviewPageComponent` : accueil du tableau de bord.
- **`profile/`** — `ProfilePageComponent` (F3.2, route enfant `profil`) : recharge
  le profil frais (`GET /users/me`) au montage, édite l'identité
  (`PATCH /users/me`, erreurs 422 par champ), liste et dépose les pièces
  justificatives (`GET`/`POST /users/me/documents`), et supprime le compte
  (`DELETE /users/me`). S'appuie sur le nouveau
  [`AccountService`](../../core/api/account.service.ts) et sur
  `AuthService.setCurrentUser()` (synchronise le nom affiché dans l'en-tête
  après édition, sans recharger la page).

### Points d'intégration

- **Route racine** : la branche `mon-espace` est déclarée dans
  [`../../app.routes.ts`](../../app.routes.ts) (avant la branche `''`, comme
  `auth`), en chargement à la demande.
- **Guard** : [`../../core/guards/auth.guard.ts`](../../core/guards) — sans
  session, redirige vers `/auth/connexion?redirect=…`.
- **En-tête conscient de la session** : le header
  ([`../../shared/components/header`](../../shared/components/header)) lit
  `AuthService.isAuthenticated` et bascule « Connexion » ↔ « Mon espace » +
  « Déconnexion » (desktop et menu mobile).
- **Vérification du compte** : l'accueil calcule `isVerified` à partir de
  `email_verified_at || phone_verified_at` (même règle que les formulaires
  
  auth-gated de F2.7) et invite à `/auth/verification` si besoin.

### Styles

- Ossature du cadre (grille barre latérale + contenu) : dans le `.scss` du
  layout. Briques partagées entre écrans de l'espace — **en-tête d'écran**
  (`.account-head/.account-eyebrow/.account-title/.account-lead`), **bandeau de
  vérification** (`.account-verify`) et étiquette « Bientôt »
  (`.account-soon-tag`) — dans le partiel global
  [`styles/_account.scss`](../../../styles/_account.scss) (`@use` dans
  `styles.scss`). L'accueil garde ses tuiles, et le Profil ses cartes (`.pf-*`),
  en styles propres.

### À savoir

- Le **jeton est en mémoire seule** (décision F2.9) : au rechargement complet de
  la page, la session est perdue et le guard renvoie vers la connexion — normal
  pour une zone privée.
