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
