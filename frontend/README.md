# Kaikun 360 — Frontend (site web Angular)

> **En une phrase :** c'est la **partie visible** de Kaikun 360 — le site web avec
> lequel les utilisateurs interagissent réellement (les pages, les boutons, les
> formulaires).

---

## À quoi sert ce dépôt (expliqué simplement)

Si le [backend](../backend/README.md) est le **moteur** invisible, ce dépôt est la
**carrosserie et le tableau de bord** : tout ce que la personne voit et manipule à
l'écran. Quand un utilisateur clique sur « Se connecter » ou remplit un
formulaire, c'est ce site qui l'affiche, puis qui va poser la question au moteur
(via l'API) et afficher la réponse.

Il est construit avec **Angular** (une technologie de sites web modernes). Le site
est pensé **« mobile d'abord »** : il doit être agréable et rapide sur téléphone,
puisque la majorité des Sénégalais navigueront depuis leur smartphone.

### Où en est le site ?

- ✅ **Le socle graphique** (la charte Kaikun : couleurs, typographies, boutons,
  cartes…) et la structure de navigation sont en place.
- ✅ **L'authentification** : créer un compte (avec choix du profil), se connecter,
  vérifier son compte par code, récupérer un mot de passe oublié. 👉 Détail :
  [`src/app/features/auth/README.md`](src/app/features/auth/README.md).
- ✅ **Les pages publiques** : accueil, univers (immobilier, nuitées, tourisme,
  transport…), moteur de recherche et catalogue, fiches détaillées, pages de
  conversion, formulaires intelligents, FAQ, contact et pages légales. Les cartes
  du catalogue et de l'accueil portent un **cœur de favori** (tous univers) :
  le client connecté ajoute/retire d'un clic ; un visiteur anonyme est invité à
  se connecter. 👉 Détail : [`src/app/features/README.md`](src/app/features/README.md).
- ✅ **Le rendu côté serveur (SSR)** : les pages publiques sont d'abord
  **assemblées par un serveur** puis envoyées prêtes à afficher (bon pour le
  référencement Google et pour un premier affichage rapide). Voir « SSR » ci-dessous.
- ✅ **L'espace client (F3)** : l'espace personnel de la personne connectée, sous
  `/mon-espace` (menu latéral sombre, en-tête épuré). Ses **six écrans** sont en
  place — tableau de bord, **profil** (identité, coordonnées, sécurité, pièces),
  **mes demandes** (liste + détail cliquable), **réservations** (liste + détail cliquable), **favoris** (tous univers, avec le cœur du
  catalogue pour les ajouter), **notifications** et **messagerie** (conversations
  + fil de discussion avec réponse). 👉 Détail :
  [`src/app/features/account/README.md`](src/app/features/account/README.md).
- ✅ **L'espace propriétaire (F4, terminé)** : sous `/espace-proprietaire`,
  réservé au rôle « propriétaire ». Il réutilise **le même habillage** que
  l'espace client (menu latéral sombre + en-tête épuré), désormais **généralisé
  en un shell partagé** (`layouts/space-layout/`, paramétré par espace) pour
  servir aussi les futurs espaces pro. Écrans livrés : le **tableau de bord de
  gestion locative** (F4.1 — mandats actifs, loyers encaissés / impayés,
  dépenses, reversements, incidents ouverts) et **« Mes biens »** (F4.2 — liste
  de tous ses biens **quel que soit leur statut** avec une pastille de **suivi de
  validation** — publié / en attente / rejeté — et une fiche détaillée), puis le
  **dépôt et l'édition d'un bien** (F4.3 — un même formulaire pour créer et
  modifier, avec le **mode de location** mensuelle / nuitées / mixte qui pilote
  les champs affichés et les appels d'enregistrement, et une localisation en
  cascade région → département → commune). Le propriétaire y **illustre ses
  biens** : dépôt de plusieurs photos, choix de l'image de couverture et retrait
  — ces photos alimentent sa fiche, les **cartes du catalogue** et la **galerie
  des fiches publiques** (bien et nuitées), un bien sans photo gardant la
  vignette dégradée de repli. Enfin la **gestion locative** (F4.4 — en lecture
  seule) : la liste de ses **mandats** puis la **fiche d'un mandat** avec un
  résumé financier, les loyers / reversements / incidents récents et un **rapport
  mensuel** recalculable par mois (loyers encaissés, commission Kaikun, **net à
  reverser**). Enfin les **Documents** (F4.5) : par bien, le propriétaire liste,
  **dépose** (titre foncier / bail / plan, PDF ou image ≤ 5 Mo), **télécharge**
  (lien signé temporaire) et **supprime** les pièces justificatives — la liste
  des biens affiche le nombre de documents de chacun. 👉 Détail :
  [`src/app/features/owner/README.md`](src/app/features/owner/README.md).
- 🏗️ **L'espace prestataire (F5, en cours)** : sous `/espace-prestataire`,
  réservé au rôle « prestataire ». Il réutilise **le même shell partagé** que les
  espaces client et propriétaire (`layouts/space-layout/`). Écran livré : le
  **tableau de bord** (F5.1 — `GET /providers/mine`) qui affiche l'**état du
  dossier prestataire** — statut de validation (en attente / validé / refusé /
  suspendu), note moyenne, avis reçus, certifications (vérifiées ou en cours) et
  avertissements. **« Missions reçues »** (F5.2 —
  `GET /provider-missions/mine`) : la liste paginée des missions confiées, avec
  montant, commission Kaikun, **net** prestataire, date prévue et statut, plus des
  **actions** de transition (accepter / refuser une mission affectée, la démarrer,
  la marquer terminée). **« Revenus & commissions »** (F5.3 —
  `GET /provider-missions/earnings`) : la synthèse financière en deux blocs, le
  **réalisé** (missions terminées : chiffre d'affaires, commission Kaikun, net
  encaissé) et l'**à venir** (missions acceptées ou en cours). Les rubriques **Mes
  services**, **Disponibilités** et **Avis reçus** sont annoncées « Bientôt » et
  se brancheront en F5.4 → F5.5. 👉 Détail :
  [`src/app/features/pro/README.md`](src/app/features/pro/README.md).
- 🚧 **À venir** : la suite de l'espace prestataire, l'espace entreprise, puis le
  back-office.

---

## Comment le site est organisé

Le code vit dans `src/app/`, rangé par responsabilité. Voici la carte des lieux,
en clair :

| Dossier | Rôle, en clair |
| --- | --- |
| [`features/`](src/app/features/README.md) | Les **écrans** regroupés par grande fonctionnalité (connexion, accueil, plus tard les catalogues…). |
| [`layouts/`](src/app/layouts/) | Les **cadres** qui entourent les pages (en-tête + pied de page du site, ou l'écran dédié à la connexion). |
| [`shared/`](src/app/shared/) | Les **briques d'interface réutilisables** (cartes de bien, badges « vérifié », galerie photo…) utilisées un peu partout. |
| [`core/`](src/app/core/) | La **plomberie invisible** : la gestion de la session (qui est connecté), la communication sécurisée avec le moteur, le contrôle des accès. |
| [`models/`](src/app/models/) | La **description des données** échangées avec le moteur (à quoi ressemble un « bien », une « réservation »…), pour éviter les erreurs. |

Chaque dossier a son propre `README.md` détaillé. Le principe : une fonctionnalité
peut s'appuyer sur `core` et `shared`, mais **ne dépend jamais d'une autre
fonctionnalité** — ainsi on peut faire évoluer une partie sans casser les autres.

### Un choix de sécurité à connaître

La « clé d'accès » d'un utilisateur connecté (le jeton) est gardée **en mémoire
vive uniquement**, jamais enregistrée durablement dans le navigateur. C'est plus
sûr (utile sur un poste partagé), au prix d'un compromis assumé : **recharger la
page déconnecte** — quitte à ajouter plus tard une reconnexion automatique.

---

## Détails techniques

- **Angular 22**, composants **standalone** (sans NgModules), **TypeScript strict**.
- Réactivité par **signals** ; composants en `ChangeDetectionStrategy.OnPush`.
- Chargement **à la demande** (lazy loading) des fonctionnalités pour un premier
  affichage rapide.
- Le point d'entrée `App` est réduit à un `<router-outlet>` ; chaque route choisit
  son **layout** (cadre).
- Design system maison : jetons de style dans
  [`src/styles/_tokens.scss`](src/styles/_tokens.scss), primitives (boutons,
  champs de formulaire, cartes…) dans [`src/styles/_base.scss`](src/styles/_base.scss).
- Adresse de l'API du moteur configurée dans `src/environments/`.

### Rendu côté serveur — SSR (F2.9)

Le site est rendu **côté serveur** (`@angular/ssr`, `outputMode: server`) : à
chaque visite, un petit serveur **Node/Express** ([`src/server.ts`](src/server.ts))
assemble le HTML complet de la page **avant** de l'envoyer au navigateur, puis
Angular « hydrate » ce HTML (le rend interactif sans le reconstruire). Concrètement :

- **Toutes les pages publiques** sont en `RenderMode.Server` (rendu à la demande,
  voir [`src/app/app.routes.server.ts`](src/app/app.routes.server.ts)) — choix
  adapté à des pages dynamiques (`/immobilier/:id`, `/pages/:slug`, `/recherche`)
  et alimentées par le backend.
- Les données lues pendant le rendu serveur sont **transférées au client**
  (transfer-cache HTTP, actif via `provideClientHydration`) : le navigateur ne
  refait pas les mêmes appels API. `withFetch()` est activé pour le HttpClient.
- Le jeton de session vivant **en mémoire seulement**, le serveur rend toujours la
  vue « visiteur non connecté » — exactement ce qu'un moteur d'indexation doit voir.

```bash
# 1. Construire (produit dist/kaikun360/{browser,server})
npx ng build

# 2. Lancer le serveur SSR → http://localhost:4000/  (port réglable via PORT)
npm run serve:ssr:kaikun360
```

> ⚠️ **Sécurité (déploiement)** : `angular.json → build.options.security.allowedHosts`
> ne contient pour l'instant que `localhost`. **Ajouter le(s) domaine(s) de
> production** (ex. `kaikun360.sn`) dans cette liste avant la mise en ligne, sinon
> le serveur SSR renverra `400 Bad Request` (protection anti-SSRF sur l'en-tête Host).

### Commandes utiles

```bash
# Serveur de développement (rechargement à chaud) → http://localhost:4200/
npx ng serve

# Construire la version optimisée (résultat dans dist/)
npx ng build

# Lancer les tests
npx ng test
```

> Node ≥ 22 requis. Le projet utilise `npx` (pas d'installation globale d'Angular
> CLI nécessaire).
