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

- **Le cadre — app-shell (F3.1, refondu F3.6, généralisé F4)** — [`../../layouts/space-layout`](../../layouts/space-layout) :
  un **menu latéral SOMBRE, collé à gauche et pleine hauteur** (rail de tableau
  de bord), et une **colonne contenu** (en-tête + section). Le rail porte la
  **marque** (en haut), la **navigation** des rubriques (item actif = fond clair
  + barre d'accent bleue) et un **pied** « Retour au site » + « Se déconnecter ».
  **Ni méga-menus ni pied de page** du site public (choix UX : l'espace doit se
  sentir « chez soi »). En **petit écran** (< 860px), le rail devient un
  **tiroir plein écran** qui glisse depuis la gauche (hamburger de l'en-tête +
  fond assombri). Depuis F4, ce cadre est un **shell générique paramétré par
  espace** (jeton `SPACE_CONFIG`) : l'espace client fournit sa config
  `CLIENT_SPACE` ([`client-space.ts`](client-space.ts)) au layout partagé.
- **L'en-tête (F3.1, refondu F3.6, généralisé F4)** — [`../../layouts/space-layout/space-header.ts`](../../layouts/space-layout/space-header.ts) :
  barre supérieure de la **colonne contenu** (à droite du rail, ne le recouvre
  pas). Contient le **titre** de l'espace (« Espace client »), la **cloche de
  notifications** (F3.6, pastille de non-lues) et le **menu utilisateur** (avatar
  + nom → **Mon profil** [F3.2] et **Se déconnecter**). Titre et cibles des liens
  proviennent de `SPACE_CONFIG`. La marque et le lien « Retour au site » vivent
  dans le rail.
- **La page d'accueil (F3.1, enrichie « comprendre son espace »)** —
  [`overview/`](overview) : salutation, un **mot de bienvenue** qui explique en
  clair à quoi sert l'espace et comment y naviguer (**masquable**, mémorisé ;
  lien « Besoin d'aide ? » pour le rouvrir), une checklist **« Pour bien
  démarrer »** dont les étapes (vérifier le compte, compléter le profil) se
  **cochent automatiquement** et qui **disparaît** une fois tout fait, et des
  **tuiles** vers chaque rubrique. Le rappel de vérification autrefois séparé est
  désormais **l'étape 1 de la checklist** (plus de double nudge).
- **La rubrique Profil (F3.2 / F3.2b / F8.0)** — [`profile/`](profile) : en tête,
  la **photo de profil** — ou le **logo**, pour un compte entreprise (F8.0) —
  puis **identité &
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
  négociation → clôturé) : étapes franchies, étape courante, étapes à venir.
  **Cliquer une carte ouvre le détail** de la demande
  (`/mon-espace/demandes/:id`, `GET /requests/{id}` réservé au propriétaire) :
  même récapitulatif en plein écran. Un bouton **« ← Retour »** (composant
  partagé `app-back-link`) est présent **sur la liste comme sur le détail** : il
  revient à la **page précédente** — donc aux **notifications** quand on arrive
  par une notification, à la liste depuis le détail, au tableau de bord depuis le
  menu. Le **dépôt** de demande reste sur les pages publiques (fiches de
  biens/services) — cet écran ne fait qu'en **suivre l'avancement**.

- **La rubrique Réservations (F3.4)** — [`bookings/`](bookings) : liste paginée
  des réservations du client, tous univers confondus (`GET /bookings/my`).
  Chaque réservation est une **carte** (univers, élément réservé, dates,
  voyageurs, montant, caution, statut teinté). Le client peut **annuler** une
  réservation lorsque le backend le permet (`cancellable` — véhicules et
  expériences non encore annulés) : la confirmation inline déclenche l'endpoint
  propre à l'univers et affiche l'éligibilité au remboursement. Les nuitées et
  trajets n'ont pas d'annulation client (pas d'endpoint) : ils restent en
  lecture seule. **Cliquer une carte ouvre le détail** de la réservation
  (`/mon-espace/reservations/:id`, `GET /bookings/{id}` réservé au titulaire),
  en lecture seule (l'annulation reste sur la liste où l'action inline est
  câblée). Un bouton **« ← Retour »** (composant partagé `app-back-link`) est
  présent **sur la liste comme sur le détail** : il revient à la **page
  précédente** — aux **notifications** quand on arrive par une notification, à la
  liste depuis le détail, au tableau de bord depuis le menu.

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
  **cloche de l'en-tête** (`space-header`) porte une **pastille** du nombre de
  non-lues. ⚠️ Depuis **F8.13**, elle ne dépend plus d'une navigation pour bouger :
  elle se met à jour toute seule (relève d'une minute tant que l'onglet est
  visible) et s'éteint dès qu'on marque une notification comme lue, ici ou
  ailleurs — la rubrique **Messages** du rail a reçu la même pastille, qui, elle,
  n'existait tout simplement pas.

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

  ⚠️ **F8.12 — le geste d'OUVRIR un fil.** Jusque-là, la messagerie savait tout
  faire sauf commencer : aucun écran n'ouvrait de conversation, tous les fils
  visibles venaient du seeder, et l'état vide décrivait un bouton qui n'existait
  nulle part. L'écran porte désormais
  [`app-contact-support`](../../shared/components/contact-support/contact-support.ts)
  (état vide + barre d'actions), tout comme la **fiche d'une demande** et la
  **fiche d'une réservation** — qui joignent en plus le dossier concerné au fil.
  Le client **ne choisit pas son interlocuteur** : le serveur lui assigne un
  agent, dont le fil affiche le **nom** (« Avec Awa Diop, support Kaikun »). Un
  fil clôturé par l'équipe reste ouvert à l'écriture — écrire le **rouvre**, et
  le bandeau le dit.
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
- **En-tête partagé** — `SpaceHeaderComponent` (`app-space-header`, dans
  [`../../layouts/space-layout`](../../layouts/space-layout)) : **barre
  supérieure** de la colonne contenu (titre + cloche de notifications + menu
  utilisateur). Injecte `AuthService` (identité), `NotificationService` (compteur
  de non-lues, rechargé à chaque `NavigationEnd`) et `SPACE_CONFIG` (titre,
  cibles des liens) ; menu utilisateur déroulant (signal `userMenuOpen`, fermé à
  la navigation / Échap / clic extérieur). Émet `sidebarToggle` (hamburger, petit
  écran). La **marque**, le **retour au site** et la **déconnexion en pied**
  vivent dans le **rail** du `space-layout`, pas ici.
- **`client-space.ts`** — `CLIENT_SPACE` (`SpaceConfig`) : la config de l'espace
  client passée au shell générique (marque « Espace client », rubriques
  `ACCOUNT_NAV`, aide, cloche/profil sous `/mon-espace`).
- **`account.routes.ts`** — `ACCOUNT_ROUTES` : le layout partagé
  `SpaceLayoutComponent` porte `canActivate: [authGuard]` et **fournit**
  `SPACE_CONFIG = CLIENT_SPACE` (via `providers`) ; les sections sont ses routes
  enfants (chargées à la demande). L'accueil est la route enfant `''`.
- **`overview/`** — `AccountOverviewPageComponent` : accueil du tableau de bord.
  **Mot de bienvenue** masquable (préférence en `localStorage`, clé
  `kaikun.account.welcomeDismissed`, lecture/écriture **SSR-safe** via garde
  `typeof window`) + lien « Besoin d'aide ? » pour le rouvrir. Checklist **« Pour
  bien démarrer »** = `steps` (computed) dont les `done` sont **dérivés de
  `AuthService.user()`** (aucun appel réseau) : `isVerified`
  (email/phone_verified_at) et `profileComplete` (téléphone + une localisation) ;
  la section se retire quand `allStepsDone`. Le bandeau `.account-verify` n'est
  plus utilisé ici (fondu dans l'étape 1).
- **`profile/`** — `ProfilePageComponent` (F3.2 / F3.2b / F8.0, route enfant
  `profil`) : recharge le profil frais (`GET /users/me`) au montage ;
  **photo de profil / logo d'entreprise (F8.0)** en tête de page —
  `POST`/`DELETE /users/me/avatar`, dépôt **immédiat** au choix du fichier (pas
  de bouton « Envoyer » séparé : contrairement aux pièces justificatives, il n'y
  a pas de *type* à choisir, un second clic n'apporterait rien). La page est
  montée dans les **quatre** espaces : c'est le backend qui dit s'il attend une
  photo ou un logo (`profile.avatar_kind`), l'interface ne le devine pas depuis
  le rôle. Le champ fichier est **remis à zéro dès la sélection**, sinon
  rechoisir le même fichier après une erreur ne déclencherait aucun `change`.
  Après un dépôt, `applyUser()` range l'utilisateur renvoyé dans l'état local
  **et** dans `AuthService` — sans quoi l'ancienne image resterait dans
  l'en-tête jusqu'au prochain rechargement. Ensuite : édite identité **et
  coordonnées** (`PATCH /users/me`, erreurs 422 par champ). **E-mail / téléphone
  (F3.2b)** : un changement renvoie `verification.{email,phone}_required` → un
  **panneau de saisie de code** apparaît (réutilise `AuthService.verify` /
  `sendVerificationCode`). Le panneau du téléphone suit
  `verification.phone_delivery` : le code partant par **e-mail** tant que le SMS
  n'est pas branché, la phrase change en conséquence — annoncer un SMS ferait
  attendre l'utilisateur devant un téléphone muet. **Localisation en cascade** via
  [`GeoService`](../../core/api/geo.service.ts) (Région → Département → Commune,
  préremplie sans déclencher les resets grâce à `emitEvent:false`). **Sécurité**
  : mot de passe via `AccountService.updatePassword` (`PATCH /users/me/password`).
  Liste/dépôt de pièces (`GET`/`POST /users/me/documents`) et suppression
  (`DELETE /users/me`). S'appuie sur le
  [`AccountService`](../../core/api/account.service.ts) et sur
  `AuthService.setCurrentUser()` (synchronise le nom de l'en-tête sans reload).
- **`requests/`** (F3.3) — deux écrans via le
  [`RequestService`](../../core/api/request.service.ts) :
  - **`RequestsPageComponent`** (route enfant `demandes`) : liste paginée
    (`myRequests`). Chaque carte est un **lien** (`.rq-card-link`) vers le
    détail (`['/mon-espace/demandes', req.id]`) ; la **chronologie** reste hors
    du lien.
  - **`RequestDetailPageComponent`** (route enfant `demandes/:id`) : charge la
    demande (`get(id)` → `GET /requests/{id}`) dans un `switchMap` sur
    `paramMap`, avec états `loading/ready/notfound/forbidden/failed` (403 = pas
    la mienne). Bouton retour **historique** (`app-back-link`) — présent aussi en
    tête de la **liste** — pour revenir à la page précédente (notifications,
    liste, tableau de bord…).
  - **`request-timeline.ts`** — **source unique** des étapes (`REQUEST_STEPS`,
    miroir de `RequestStatus`) et du calcul d'état d'étape (`stepState`),
    partagée par les deux écrans (plus de duplication).
- **`bookings/`** (F3.4) — trois écrans via le
  [`BookingService`](../../core/api/booking.service.ts). ⚠️ **Ils ne sont plus
  propres à l'espace client** (F8.14) : ils dérivent tous leurs liens de
  `SPACE_CONFIG` (`bookingsBase`), comme le font les écrans Messages,
  Notifications et Profil depuis F4, et sont **montés tels quels dans l'espace
  entreprise**. Écrire `/mon-espace` en dur les rendait inutilisables ailleurs —
  or `/mon-espace` est gardé par le rôle `client`, un compte entreprise y aurait
  été refoulé au moment de payer. Le seul élément qui ne se transpose pas est
  **l'état vide**, piloté par `SpaceConfig.bookingsEmpty` (`catalogue` pour un
  client, `devis` pour une entreprise — un séminaire ne s'achète pas sur étagère) :
  - **`BookingsPageComponent`** (route enfant `reservations`) : liste paginée
    (`myBookings`). Chaque carte est un **lien** (`.bk-card-link`) vers le
    détail (`[bookingsBase, bk.id]`) ; la **notice de
    remboursement** et le **bloc d'annulation** restent hors du lien pour rester
    cliquables. **Annulation** propre à l'univers via `cancel(type, id)`.
  - **`BookingDetailPageComponent`** (route enfant `reservations/:id`) : charge
    la réservation (`get(id)` → `GET /bookings/{id}`) dans un `switchMap` sur
    `paramMap`, avec états `loading/ready/notfound/forbidden/failed` (403 = pas
    la mienne). **Lecture seule** (l'annulation vit sur la liste), bouton retour
    **historique** (`app-back-link`) — présent aussi en tête de la **liste**.
  - **`BookingPaymentPageComponent`** (route enfant `reservations/:id/paiement`,
    F8.6) : écran **dédié** au règlement — payer engage de l'argent, un bouton
    posé sur une carte de liste ferait partir un client d'un clic malheureux.
    ⚠️ **Un seul chemin proposé depuis F8.14.a** : paiement en ligne, montant
    intégral. Le **transfert Wave/OM** et l'**acompte** sont **masqués, pas
    supprimés** — deux booléens documentés dans le composant
    (`manualTransferEnabled`, `partialPaymentEnabled`). Le serveur continue de
    les accepter (`mode: 'manuel'`, montants partiels) et les tests les couvrent :
    les rétablir est une bascule, pas un développement. Raisons de la décision :
    un transfert manuel n'est confirmé qu'après le passage d'un agent (le client
    croit avoir payé alors que sa réservation attend), et l'acompte est réservé à
    de futures **dérogations** accordées au cas par cas à des clients fidèles —
    l'ouvrir à tous reviendrait à l'accorder à tout le monde. ⚠️ Le règlement
    Wave/OM reste possible : il se **constate au back-office** quand un client
    transfère de lui-même.
  - **`booking-display.ts`** — **source unique** de la tonalité de statut
    (`bookingTone`), partagée par les deux écrans (plus de duplication).
- **`notifications/`** — `NotificationsPageComponent` (F3.6, route enfant
  `notifications`) : liste paginée via
  [`NotificationService`](../../core/api/notification.service.ts)
  (`myNotifications` renvoie la pagination **enrichie** de `unread_count`). Au
  clic sur une carte, `markAsRead` puis navigation vers `action_url` ;
  `markAllAsRead` remet toutes les non-lues à lu. ⚠️ **Le compteur n'est plus tenu
  par l'écran ni par l'en-tête** : depuis F8.13 il vit dans
  [`core/state/unread-store.ts`](../../core/state/unread-store.ts), source unique
  de la cloche **et** de la pastille « Messages » du rail. Il se réveille à la
  session, à chaque navigation et par une relève d'une minute ; cet écran, qui
  fait *baisser* le compteur, le **pousse** (`setNotifications`) pour que la
  pastille s'éteigne dans le même geste que le clic. Auparavant, il était compté
  dans l'en-tête et ne bougeait qu'à la navigation.
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
  (`space-layout.html`, à côté de « Retour au site » / « Se déconnecter »),
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
  vérification** (`.account-verify`), étiquette « Bientôt »
  (`.account-soon-tag`), **espacement du bouton retour** (`.account-back`, pour
  `app-back-link`), **briques d'une demande** (`.rq-card`, `.rq-status`,
  `.rq-facts`, `.rq-timeline`…) et **briques d'une réservation** (`.bk-card`,
  `.bk-status`, `.bk-facts`…) — toutes **partagées par la liste et le détail** —
  dans le partiel global [`styles/_account.scss`](../../../styles/_account.scss)
  (`@use` dans `styles.scss`). Chaque liste garde ses styles propres (`.rq-empty`
  / `.bk-empty`, `.rq-card-link` / `.bk-card-link`, pager, annulation). Le bouton
  retour porte ses propres styles (composant `app-back-link`). L'accueil garde
  ses tuiles, et le Profil ses cartes (`.pf-*`), en styles propres.

### À savoir

- Le **jeton est en mémoire seule** (décision F2.9) : au rechargement complet de
  la page, la session est perdue et le guard renvoie vers la connexion — normal
  pour une zone privée.

## Régler une réservation (F8.6)

| Route | Écran |
|---|---|
| `/mon-espace/reservations/:id/paiement` | Règlement d'une réservation |
| `/paiement/succes` · `/paiement/annule` | Retour de PayTech (**publiques**, hors espace) |

⚠️ **Ce chaînon manquait entièrement.** Le backend savait initier un paiement
depuis B14 et le back-office les supervisait depuis F7.2, mais **aucun écran du
site n'appelait `POST /payments/initiate`** : un client pouvait réserver sans
jamais pouvoir payer.

**Un écran dédié, pas un bouton dans une liste.** Payer engage de l'argent :
l'utilisateur voit le total, ce qu'il a déjà versé et le reste dû avant qu'on ne
le sorte du site. Un bouton posé sur une carte de liste ferait partir un client
d'un clic malheureux — la fiche réservation n'affiche donc qu'un rappel du reste
dû et un lien vers cet écran.

**Deux moyens.** *En ligne* : le serveur crée la demande et renvoie une URL ; on
quitte l'application (`window.location.assign`, ce n'est pas une navigation
Angular). *Wave / Orange Money* : aucun appel au PSP, le serveur renvoie la
marche à suivre — le numéro vient du paramétrage back-office, **jamais écrit en
dur** — et un agent confirmera l'encaissement.

**L'acompte** est proposé parce que le backend le gère : le montant est plafonné
au reste dû **côté serveur**. Quand le client solde tout, le montant est **omis**
de la requête : le serveur recalcule alors le reste dû lui-même, ce qui évite
qu'un paiement arrivé entre-temps ne fasse échouer le règlement.

⚠️ **Les pages de retour sont publiques et hors espace client, à dessein** : le
client revient d'un autre domaine, parfois longtemps après. Une garde de rôle le
renverrait vers la connexion juste après avoir payé. Elles n'affichent aucune
donnée de réservation et **n'affirment jamais que le paiement est acquis** — une
redirection de navigateur ne prouve rien, seul l'IPN signé fait foi.

## Donner son avis (F8.15.a)

| Route | Écran |
|---|---|
| `<espace>/reservations/:id/avis` | Dépôt d'un avis sur une réservation terminée |

⚠️ **`POST /reviews` existait depuis B12.2 et n'avait aucun appelant.** Le client
n'avait nulle part où noter, le back-office « Avis et qualité » modérait une file
que rien n'alimentait, et la note des prestataires ne pouvait jamais monter — le
cahier des charges demande pourtant des avis sur les fiches (§4.2), la notation
des prestataires (Kaikun Pro) et leur modération (§6).

⚠️ **Le trou était plus profond qu'un écran manquant : aucune réservation ne
devenait jamais `terminee`.** La policy serveur exige un service consommé ; or ni
le check-in/check-out, ni l'encaissement, ni aucun geste ne posaient ce statut —
`en_cours` et `terminee` étaient des états morts. Livrer le formulaire seul
n'aurait donné le droit d'écrire à personne. Le cycle est fermé côté serveur (la
tâche planifiée `reservations:cloturer` et le check-out d'un agent), **ce qui
suppose un cron en production** : sans lui, le bouton n'apparaît jamais.

**On note la chose réservée, pas la réservation.** La cible est le logement, le
véhicule ou l'expérience (`reviewable_type`/`reviewable_id`, servis par
`BookingResource`) : deux séjours dans le même logement visent le même avis, et
le serveur n'en accepte qu'un. Les trajets et le sur-mesure ne se notent pas —
il n'y a pas de fiche à noter.

⚠️ **`GET /reviews` ne dit pas si j'ai déjà donné mon avis** : il ne renvoie que
les avis **publiés**, or tout avis frais est en modération. L'écran s'appuie donc
sur `GET /reviews/mine` et affiche l'avis existant au lieu du formulaire. La
liste et la fiche, elles, ne le savent pas : le vérifier par ligne coûterait une
requête par carte — elles n'affichent le bouton que sur `can_review`, miroir
exact de la policy, et laissent l'écran d'avis trancher.

**Écran dédié, comme le règlement** : écrire un avis demande de se souvenir du
séjour, ce qu'un champ coincé entre deux cartes n'invite pas à faire. La
réservation est rappelée au-dessus du formulaire. Les étoiles sont de **vrais
boutons** portant leur libellé (`aria-label`), pas un `input[type=range]`.
