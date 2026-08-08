# `core/` — Socle applicatif (singletons)

Services et briques **transverses instanciés une seule fois** pour toute
l'application (approche standalone, sans NgModule) :

- **services/** — `AuthService` (session ; jeton en `sessionStorage`, réhydraté et
  revalidé au démarrage), services d'API.
- **api/** — accès HTTP typés au backend `/api/v1` :
  - `CatalogService` — catalogues publics (index paginés) **et** détail
    (`property`, `stay`, `stayAvailability` — F2.1/F2.3 ; `experience`,
    `experienceAvailability`, `vehicle` — F2.4).
  - `RequestService` — dépôt de demandes contextuelles `POST /requests`
    (demande de visite/réservation ; **auth requise**) — F2.3 ; **et suivi** des
    demandes du client connecté `GET /requests/my` (paginé) — F3.3.
  - `BookingService` — réservations du client `GET /bookings/my` (paginé) et
    **annulation** propre à l'univers `PATCH /vehicles|experiences/bookings/{id}/cancel`
    (véhicules et expériences uniquement) — F3.4.
  - `FavoriteService` — favoris du client `GET /favorites` (paginé) et
    ajout/retrait `POST`/`DELETE /properties/{id}/favorite` (biens immobiliers) — F3.5.
  - `ReviewService` — avis publiés `GET /reviews` d'une entité notée — F2.3.
  - `WhatsAppService` — lien WhatsApp contextuel `GET /whatsapp/link` (message
    prérempli + numéro de support paramétré côté back-office) — F2.6.
  - `QuoteService` — consultation & réponse à un devis `GET`/`PATCH /quotes/{id}`
    (accepter/refuser ; **auth requise**) — F2.7.
  - `ProviderService` — inscription prestataire `POST /providers` (**auth +
    compte vérifié**) et suivi `GET /providers/mine` (404 = pas encore
    inscrit) — F2.7.
  - `GeoService` — référentiel géographique `GET /regions|departments|communes`
    pour les sélecteurs en cascade (région → département → commune) — F2.7.
  - `PropertyManagementService` — dépôt de bien `POST /properties` (**auth +
    compte vérifié** ; le bien part en file de validation) — F2.7.
  - `ConstructionService` — simulateur de budget `POST /construction-requests/simulate`
    (**public**) : chiffrage complet (travaux, frais annexes, foncier, délai,
    jalons, rentabilité) calculé et barème géré côté backend — F2.5 (enrichi).
  - `ContentService` — contenu éditorial public : FAQ publiée `GET /faqs` et
    pages de contenu par slug `GET /pages/{slug}` (le backend enveloppe la page
    sous `data.page` ; le service l'aplatit) — F2.8.
  - `ContactService` — envoi d'un message de contact `POST /contact` (**public**,
    throttlé) traité par l'équipe, **et** coordonnées publiques du siège
    `GET /contact-info` (adresse + lat/long pour la carte, issues des réglages
    back-office) — F2.8.1.
  - `AccountService` — compte de l'utilisateur connecté (espace client, F3.2 /
    F3.2b) : profil frais `GET /users/me`, mise à jour `PATCH /users/me`
    (renvoie l'utilisateur **+ les canaux à re-vérifier** si e-mail/téléphone ont
    changé), **changement de mot de passe** `PATCH /users/me/password`,
    suppression (anonymisation RGPD) `DELETE /users/me`, et pièces justificatives
    `GET`/`POST /users/me/documents` (dépôt **multipart** PDF/JPG/PNG).
- **interceptors/** — `tokenInterceptor` (ajoute le Bearer), `errorInterceptor`
  (gestion centralisée : 401 → login, 422 → erreurs de formulaire, 500 → page
  d'erreur), et `serverApiOriginInterceptor` (F9.1).
  - ⚠️ **`serverApiOriginInterceptor` corrige un défaut du rendu serveur né avec
    F2.9, entièrement silencieux.** En production `environment.apiUrl` vaut
    `/api/v1` — une adresse **relative**, qui ne se résout que dans un
    navigateur. Le processus Node du rendu serveur l'adressait donc à sa propre
    origine (le port 4000), qui répond du HTML : **chaque fiche du catalogue
    répondait « introuvable » au rendu serveur**. Invisible à l'écran (le
    navigateur refait l'appel correctement à l'hydratation), mais **un robot
    n'exécute pas de JavaScript** — Google et l'aperçu WhatsApp ne voyaient que
    la page d'erreur. L'intercepteur rend l'URL absolue à partir du jeton
    `API_ORIGIN`, fourni **uniquement** par `app.config.server.ts` (valeur lue
    dans `process.env['API_ORIGIN']`). Il est donc **inerte dans le navigateur**.
    Placé **en premier** dans la chaîne, avant que quiconque lise l'URL.
- **guards/** — `authGuard`, `roleGuard` (protection des routes).
- **seo/** (F9.1) — les **balises que lisent les moteurs et les réseaux sociaux**.
  - `SeoService` — écrit `<title>`, description, `canonical`, OpenGraph, Twitter
    et les blocs **JSON-LD**. ⚠️ Il réécrit **tout le jeu à chaque page**, jamais
    par différence : une application à page unique ne recharge pas le document,
    et sans réécriture complète la photo d'un bien resterait en aperçu de la
    page Contact.
  - `SeoTitleStrategy` — remplace la stratégie de titre du routeur (le seul point
    d'extension appelé à chaque navigation ; un abonnement concurrent à
    `NavigationEnd` créerait une course sur `<title>`). Elle applique le repli
    déclaré par la route dans `data.seo`. ⚠️ **Une route sans `data.seo` est
    `noindex`** — règle de sécurité, à ne pas inverser : voir le README frontend.
  - `json-ld.ts` — constructeurs schema.org (`Organization`, `WebSite`,
    `BreadcrumbList`, `Product` + `Offer`). ⚠️ **Ne jamais y décrire ce que la
    page n'affiche pas** : Google traite l'écart comme une manipulation et
    sanctionne tout le domaine. C'est pourquoi `aggregateRating` en est absent —
    les avis existent, mais aucune fiche publique n'affiche encore de note
    moyenne. ⚠️ La devise est **`XOF`** et les montants sont des entiers.
  - ⚠️ Tout ce dossier doit rester **exécutable au rendu serveur** : ni `window`
    ni `navigator`, ni `DOMParser`. C'est précisément le rendu serveur que lisent
    les robots — et une divergence DOM serveur/client casserait l'hydratation
    (le piège de F8.7).
- **pwa/** (F9.0) — `PwaService` : la vie de l'**application installée**.
  Un manifeste et un service worker suffisent à rendre le site *installable* —
  pas à ce qu'il soit *installé* ni *à jour*. Deux comportements du navigateur
  l'expliquent, aucun n'étant intuitif :
  1. **Chrome n'installe rien tout seul** : il émet `beforeinstallprompt` et
     attend qu'on lui demande. Le service met l'événement **de côté**
     (`preventDefault()` — sinon Chrome affiche SA bannière, quand il veut et
     dans sa langue) et l'expose via le signal `installable`.
  2. **Une nouvelle version ne s'active pas d'elle-même** : le service worker la
     télécharge, puis attend que **tous** les onglets du site soient fermés. Sur
     un téléphone où l'onglet ne se ferme jamais, on peut rester des semaines sur
     une version périmée — et signaler des bugs déjà corrigés. D'où le signal
     `miseAJourPrete` et le bandeau qui propose d'actualiser.
  ⚠️ **Tout est gardé par `isPlatformBrowser`** (leçon de F8.7 : toucher `window`
  au rendu serveur lève une `ReferenceError` **silencieuse**). ⚠️ Les deux
  signaux naissent à `false` **des deux côtés**, ce qui rend le DOM identique et
  laisse l'hydratation tenir. ⚠️ `SwUpdate` est injecté en `optional` et testé
  sur `isEnabled` : en développement le service worker est désactivé, et
  l'injecter durement ferait échouer l'amorçage de toute l'application.
  ⚠️ Une invitation **consommée ne se rejoue pas** : on la jette après le clic,
  accepté ou refusé — la garder ne ferait qu'un bouton mort. 7 tests.
- **state/** — état et cycles de vie transverses :
  - `favorite-store.ts` — les favoris du client, partagés entre écrans.
  - `poll-while-visible.ts` (F8.12.a) — **relève périodique** d'un écran ouvert :
    rappelle une fonction à intervalle **tant que l'onglet est visible**, avec un
    battement immédiat au retour sur l'onglet, et **rien du tout en SSR** (un
    intervalle empêcherait la réponse serveur de se terminer). Le nettoyage est
    branché sur le `DestroyRef` local — à appeler depuis un contexte d'injection,
    il n'y a rien à défaire à la main. ⚠️ Ce n'est **pas** du temps réel : c'est
    le choix assumé de ne pas exiger un démon WebSocket permanent pour un canal
    où l'on écrit une phrase toutes les deux minutes. Utilisé par les deux fils
    de discussion (10 s) et les deux listes de la messagerie (30 s) ; l'appelant
    doit ne redemander que le nouveau (`?after=`).
  - `unread-store.ts` (F8.13) — **les compteurs de non-lus**, notifications et
    messages, en une seule source pour la cloche de l'en-tête, le rail des quatre
    espaces et celui du back-office. Se réveille sur trois signaux : ouverture /
    fermeture de session (la déconnexion **remet à zéro**), chaque navigation, et
    une relève d'une minute tant que l'onglet est visible. Les écrans qui font
    *baisser* un compteur le poussent (`setNotifications` / `setMessages`) au lieu
    d'attendre le réveil suivant — les endpoints de lecture renvoient déjà le
    nouveau total. ⚠️ Avant, le compteur de notifications vivait **dans
    l'en-tête** et ne bougeait qu'à la navigation, et celui des messages
    **n'existait nulle part** (`MessageService.unreadCount()` n'avait aucun
    appelant depuis F3.7). Tout appel est gardé par `isAuthenticated()` et par
    `isBrowser` : rien ne part d'une page publique ni du SSR.
  - `booking-intent-store.ts` (F8.13) — **le panier de réservation en cours** : ce
    qu'un visiteur a saisi sur une fiche (dates, places) avant qu'on lui demande
    de se connecter. Les quatre fiches réservables masquaient leur formulaire aux
    visiteurs : il fallait un compte pour découvrir un prix. La saisie est
    conservée en `sessionStorage` — elle doit survivre au **rechargement complet**
    de la connexion Google, et mourir avec l'onglet — puis rendue **à la seule
    fiche concernée**, **une seule fois** (elle se consomme), et **périmée au bout
    d'une heure**. Le store ne connaît pas la forme des univers : il transporte,
    la fiche interprète.

Fourni via `app.config.ts` (`provideHttpClient(withInterceptors(...))`, etc.).
Ne contient **aucun composant d'interface** (voir [`../shared`](../shared)).
