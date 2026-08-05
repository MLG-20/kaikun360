# `immo/` — Univers Immobilier (F2.3)

> **En une phrase :** tout ce qui permet à un visiteur de **parcourir les biens
> vérifiés** et de **demander une visite** en confiance.

---

## 1. Expliqué simplement

Deux écrans :

- **La page vitrine** (`/immobilier`) — un bandeau qui rappelle la promesse de
  confiance (vérification documentée, visite sur demande, protection diaspora),
  suivi du **catalogue filtrable** (type de bien, prix, mots-clés). Chaque
  résultat est une carte cliquable qui mène à sa fiche.
- **Le comparateur** (`/immobilier/comparer`) — on coche « Comparer » sur les
  cartes du catalogue (quatre au maximum), puis un tableau met les biens côte à
  côte, critère par critère, **en surlignant les lignes où ils diffèrent** : ce
  sont elles qui font le choix.
- **La fiche d'un bien** (`/immobilier/:id`) — la photo (un visuel de repli tant
  que les vraies photos ne sont pas exposées), le titre, la localisation, le
  prix, la description, les caractéristiques, un lien vers la carte, et surtout
  un **formulaire « Demander une visite »**. Pour envoyer une demande il faut
  être connecté : sinon, un bouton invite à se connecter (et ramène ensuite sur
  la fiche).

Quand la demande est envoyée, le visiteur reçoit une **référence de suivi** —
c'est le fil conducteur anti-arnaque de Kaikun 360.

---

## 2. Détails techniques

- **`property-list/`** — `PropertyListPageComponent`, route `/immobilier`.
  Réutilise `app-catalog` figé sur l'univers `immobilier` ; la mise en page vient
  des styles partagés `.uni-hero` / `.uni-catalog`
  ([`../../../styles/_universe.scss`](../../../styles/_universe.scss)).
- **`property-compare/`** — `PropertyComparePageComponent`, route
  `/immobilier/comparer` (F8.15.e). ⚠️ **Déclarée AVANT `immobilier/:id`** dans
  `app.routes.ts`, sinon « comparer » serait pris pour un identifiant de bien.
  - La sélection vit dans `core/state/compare-store.ts` (`localStorage`),
    alimentée par la case « Comparer » de `app-listing-card` et la barre
    d'action de `app-catalog` — celle-ci n'apparaît **que sur l'immobilier**,
    seul univers dont le serveur sait comparer les fiches.
  - ⚠️ **Ce n'est pas un favori** : un favori est durable et rattaché au compte,
    comparer est un geste de session ouvert aux **visiteurs anonymes** — c'est
    précisément quand on hésite entre deux biens qu'on n'a pas encore de compte.
  - ⚠️ **`GET /properties/compare` filtre en SILENCE** : biens publiés
    seulement, ids inconnus ignorés, troncature au-delà de 4. La page compare
    donc ce qu'elle a reçu à ce qu'elle a demandé et annonce les biens disparus,
    sinon une sélection vieille de trois semaines rétrécirait sans un mot. Le
    plafond de 4 est reproduit côté client pour pouvoir **refuser le cinquième
    avec un message** plutôt que d'avaler le clic.
  - ⚠️ **La barre d'action utilise `[hidden]`, jamais `@if`** : la sélection
    vient du `localStorage`, donc vide au rendu serveur et éventuellement pleine
    au client — un `@if` ferait diverger les deux DOM et l'hydratation
    échouerait (le piège du bouton Google, F8.15).
- **`property-detail/`** — `PropertyDetailPageComponent`, route `/immobilier/:id`.
  - Charge le bien via `CatalogService.property(id)` ; un bien non publié renvoie
    **404 → état « introuvable »**. `switchMap` annule la requête précédente si
    l'on navigue d'une fiche à l'autre.
  - Le formulaire de visite poste sur `POST /requests`
    (`RequestService.create`, `service_type = immo`). La date souhaitée est
    fusionnée dans le message (l'API générique ne porte pas de champ dédié).
  - L'accès au dépôt est gardé par `AuthService.isAuthenticated` (le 401 serait
    de toute façon détourné vers la connexion par l'`errorInterceptor`).
  - Les avis **ne s'appliquent pas** aux biens (uniquement nuitées/véhicules/
    expériences côté backend) → pas de section avis ici.
- Les cartes du catalogue pointent vers la fiche via l'`input [link]` de
  `app-listing-card` (défini dans `catalog.config.ts`).
