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

On a construit cet espace **rubrique par rubrique**. Il est désormais **complet** :
le **cadre**, la **page d'accueil** et les six rubriques — **Profil**, **Mes
demandes**, **Réservations**, **Favoris**, **Notifications** et **Messages** — sont
toutes en place (plus aucune section « Bientôt »), complétées d'une rubrique
**Aide** (mode d'emploi de l'espace).

### Ce qui existe aujourd'hui

- **Le cadre — app-shell (F3.1, refondu F3.6)** — [`../../layouts/account-layout`](../../layouts/account-layout) :
  un **menu latéral SOMBRE, collé à gauche et pleine hauteur** (rail de tableau
  de bord), et une **colonne contenu** (en-tête + section). Le rail porte la
  **marque** (en haut), la **navigation** des rubriques (item actif = fond clair
  + barre d'accent bleue) et un **pied** « Retour au site » + « Se déconnecter ».
  **Ni méga-menus ni pied de page** du site public (choix UX : l'espace doit se
  sentir « chez soi »). En **petit écran** (< 860px), le rail devient un
  **tiroir plein écran** qui glisse depuis la gauche (hamburger de l'en-tête +
  fond assombri).
- **L'en-tête (F3.1, refondu F3.6)** — [`account-header/`](account-header) :
  barre supérieure de la **colonne contenu** (à droite du rail, ne le recouvre
  pas). Contient un **titre** (« Espace client »), la **cloche de notifications**
  (F3.6, pastille de non-lues) et le **menu utilisateur** (avatar + nom → **Mon
  profil** [F3.2] et **Se déconnecter**). La marque et le lien « Retour au
  site » vivent désormais dans le rail.
- **La page d'accueil (F3.1, enrichie « comprendre son espace »)** —
  [`overview/`](overview) : salutation, un **mot de bienvenue** qui explique en
  clair à quoi sert l'espace et comment y naviguer (**masquable**, mémorisé ;
  lien « Besoin d'aide ? » pour le rouvrir), une checklist **« Pour bien
  démarrer »** dont les étapes (vérifier le compte, compléter le profil) se
  **cochent automatiquement** et qui **disparaît** une fois tout fait, et des
  **tuiles** vers chaque rubrique. Le rappel de vérification autrefois séparé est
  désormais **l'étape 1 de la checklist** (plus de double nudge).
- **La rubrique Profil (F3.2 / F3.2b)** — [`profile/`](profile) : **identité &
  coordonnées** (nom, **e-mail et téléphone modifiables** — un changement
  déclenche une **re-vérification** avec saisie du code sur place ; **adresse**
  + **localisation en cascade** Région → Département → Commune), **pièces
  justificatives** (liste + dépôt PDF/JPG/PNG ≤ 5 Mo, téléchargement par URL
  signée), **sécurité** (changement de mot de passe, exige le mot de passe
  actuel), et **suppression du compte** (anonymisation RGPD, derrière
  confirmation).
- **La rubrique Mes demandes (F3.3)** — [`requests/`](requests) : liste **en
  lecture seule** des demandes de service du client (`GET /requests/my`, paginée
  15/page). Chaque demande est une **carte** (référence, univers, budget
  indicatif, localité, message) surmontée d'une **chronologie de statut** qui
  matérialise la machine à états backend (reçu → vérification → visite → devis →
  négociation → clôturé) : étapes franchies, étape courante, étapes à venir. Le
  **dépôt** de demande reste sur les pages publiques (fiches de biens/services) —
  cet écran ne fait qu'en **suivre l'avancement**.

- **La rubrique Réservations (F3.4)** — [`bookings/`](bookings) : liste paginée
  des réservations du client, tous univers confondus (`GET /bookings/my`).
  Chaque réservation est une **carte** (univers, élément réservé, dates,
  voyageurs, montant, caution, statut teinté). Le client peut **annuler** une
  réservation lorsque le backend le permet (`cancellable` — véhicules et
  expériences non encore annulés) : la confirmation inline déclenche l'endpoint
  propre à l'univers et affiche l'éligibilité au remboursement. Les nuitées et
  trajets n'ont pas d'annulation client (pas d'endpoint) : ils restent en
  lecture seule.

- **La rubrique Favoris (F3.5, généralisée tous univers)** —
  [`favorites/`](favorites) : liste paginée des favoris du client — désormais
  **tous univers confondus** (biens, nuitées, véhicules, expériences, mobilité),
  car les favoris sont devenus **polymorphes** côté backend (`GET /favorites`,
  élément embarqué). Chaque favori est rendu avec la **même carte que le catalogue
  de son univers** (`app-listing-card`, mappage `UNIVERSES[…].toCard` via
  `UNIVERSE_FOR_FAVORITABLE[type]`) : cliquer mène à la fiche. Un bouton **cœur**
  en surimpression permet de **retirer** l'élément (`DELETE /favorites/{type}/{id}`)
  derrière une confirmation inline ; la carte quitte alors la liste et l'état
  partagé ([`FavoriteStore`](../../core/state/favorite-store.ts)) est resynchronisé.
  L'**ajout** se fait via le **cœur des cartes du catalogue et de l'accueil**.

- **La rubrique Notifications (F3.6)** — [`notifications/`](notifications) : centre
  de notifications du client (`GET /users/me/notifications`, paginé), avec le
  nombre de non-lues joint aux métadonnées. Chaque notification est une **carte
  cliquable** (icône teintée par catégorie, titre, message, date) : au clic on la
  **marque comme lue** (`PATCH …/{id}/read`) puis, si elle porte un `action_url`,
  on **navigue** vers l'écran concerné (demande, réservation…). Un bouton **« Tout
  marquer comme lu »** (`PATCH …/read-all`) apparaît dès qu'il reste des non-lues.
  Les notifications non lues sont mises en avant (liseré de marque, point). La
  **cloche de l'en-tête** (`account-header`) porte une **pastille** du nombre de
  non-lues, rafraîchie à chaque navigation dans l'espace.

- **La rubrique Messages (F3.7)** — [`messages/`](messages) : la **messagerie** du
  client. Un premier écran liste ses **conversations** (`GET /messages`, paginé),
  chacune montrant le **correspondant** (support Kaikun, propriétaire, prestataire),
  un **aperçu** du dernier message, la date, et une **pastille** du nombre de
  messages non lus. En ouvrant une conversation (`GET /messages/{id}`), on voit le
  **fil** de messages en **bulles** (les siens à droite en bleu, ceux du
  correspondant à gauche) et on **répond** via un composeur
  (`POST /messages/{id}/messages`) — le message s'ajoute sans recharger. Ouvrir un
  fil le **marque comme lu** (la pastille disparaît). Chaque nouveau message reçu
  génère aussi une **notification** (cloche F3.6). C'est la **dernière** rubrique
  *de données* de l'espace client.
- **La rubrique Aide** — [`help/`](help) : le **mode d'emploi** de l'espace,
  toujours disponible depuis le **pied du menu** latéral. Un **accordéon** explique, rubrique par
  rubrique, à quoi sert chaque partie et comment s'en servir, avec un lien direct
  vers chaque écran, et un bloc final « une question sans réponse ? » (messagerie
  / contact). Le **mot de bienvenue** du tableau de bord y renvoie (« Voir le
  guide complet »). Contenu **statique** (documentation utilisateur, aucun appel
  réseau).

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
  **barre supérieure** de la colonne contenu (titre + cloche de notifications +
  menu utilisateur). Injecte `AuthService` (identité) et `NotificationService`
  (compteur de non-lues, rechargé à chaque `NavigationEnd`) ; menu utilisateur
  déroulant (signal `userMenuOpen`, fermé à la navigation / Échap / clic
  extérieur). Émet `sidebarToggle` (hamburger, petit écran). La **marque**, le
  **retour au site** et la **déconnexion en pied** vivent dans le **rail** du
  `account-layout` (app-shell), pas ici.
- **`account.routes.ts`** — `ACCOUNT_ROUTES` : le layout `AccountLayoutComponent`
  porte `canActivate: [authGuard]` ; les sections sont ses routes enfants
  (chargées à la demande). L'accueil est la route enfant `''`.
- **`overview/`** — `AccountOverviewPageComponent` : accueil du tableau de bord.
  **Mot de bienvenue** masquable (préférence en `localStorage`, clé
  `kaikun.account.welcomeDismissed`, lecture/écriture **SSR-safe** via garde
  `typeof window`) + lien « Besoin d'aide ? » pour le rouvrir. Checklist **« Pour
  bien démarrer »** = `steps` (computed) dont les `done` sont **dérivés de
  `AuthService.user()`** (aucun appel réseau) : `isVerified`
  (email/phone_verified_at) et `profileComplete` (téléphone + une localisation) ;
  la section se retire quand `allStepsDone`. Le bandeau `.account-verify` n'est
  plus utilisé ici (fondu dans l'étape 1).
- **`profile/`** — `ProfilePageComponent` (F3.2 / F3.2b, route enfant `profil`) :
  recharge le profil frais (`GET /users/me`) au montage ; édite identité **et
  coordonnées** (`PATCH /users/me`, erreurs 422 par champ). **E-mail / téléphone
  (F3.2b)** : un changement renvoie `verification.{email,phone}_required` → un
  **panneau de saisie de code** apparaît (réutilise `AuthService.verify` /
  `sendVerificationCode`). **Localisation en cascade** via
  [`GeoService`](../../core/api/geo.service.ts) (Région → Département → Commune,
  préremplie sans déclencher les resets grâce à `emitEvent:false`). **Sécurité**
  : mot de passe via `AccountService.updatePassword` (`PATCH /users/me/password`).
  Liste/dépôt de pièces (`GET`/`POST /users/me/documents`) et suppression
  (`DELETE /users/me`). S'appuie sur le
  [`AccountService`](../../core/api/account.service.ts) et sur
  `AuthService.setCurrentUser()` (synchronise le nom de l'en-tête sans reload).
- **`notifications/`** — `NotificationsPageComponent` (F3.6, route enfant
  `notifications`) : liste paginée via
  [`NotificationService`](../../core/api/notification.service.ts)
  (`myNotifications` renvoie la pagination **enrichie** de `unread_count`). Au
  clic sur une carte, `markAsRead` puis navigation vers `action_url` ;
  `markAllAsRead` remet toutes les non-lues à lu. La **cloche de l'en-tête**
  (`account-header`) appelle `unreadCount` et affiche la pastille, rechargée à
  chaque `NavigationEnd` (donc mise à jour au retour de l'écran) — et seulement
  quand une session est active (pas d'appel voué au 401 côté serveur SSR).
- **`messages/`** (F3.7) — deux écrans via le
  [`MessageService`](../../core/api/message.service.ts) (socle backend générique :
  conversations à participants + messages) :
  - **`MessagesPageComponent`** (route enfant `messages`) : liste paginée des
    conversations (`myConversations` renvoie la pagination **enrichie** de
    `unread_count`). Chaque ligne est un lien vers le fil ; pastille de non-lus,
    aperçu du dernier message (préfixe « Vous : » si l'on en est l'auteur),
    étiquette de contexte éventuelle.
  - **`MessageThreadComponent`** (route enfant `messages/:id`) : ouvre le fil
    (`conversation` → marque lu côté serveur), affiche les messages en **bulles**
    alignées via `is_mine`, défile en bas (`ngAfterViewChecked`) et **répond**
    (`sendMessage`) en ajoutant le message localement (sans reload). Un fil dont
    on n'est pas participant renvoie 404 → écran d'erreur. Modèles dans
    [`../../models/message.model.ts`](../../models/message.model.ts). La catégorie
    de notification `message` (icône `chat`) a été ajoutée au centre F3.6.
- **`help/`** — `HelpPageComponent` (route enfant `aide`) : page d'aide statique.
  Les sujets sont un tableau typé `HelpTopic[]` (icône, titre, paragraphes, lien),
  rendus en **accordéon** natif (`<details>`/`<summary>`, accessible clavier,
  chevron pivotant). Aucun service. **L'entrée « Aide » n'est PAS dans
  `ACCOUNT_NAV`** : c'est un utilitaire, rendu dans le **pied du rail**
  (`account-layout.html`, à côté de « Retour au site » / « Se déconnecter »),
  pour garder la navigation principale courte (**pas de défilement du menu**) et
  hors des tuiles de l'accueil. Icône `help` ajoutée à `AccountIcon` +
  `account-icon.ts`. Le tableau de bord y renvoie depuis le mot de bienvenue.

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
