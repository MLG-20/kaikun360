# `explore/` — Univers Tourisme (F2.4)

> **En une phrase :** tout ce qui permet à un visiteur de **parcourir les
> expériences touristiques vérifiées** et de **demander une réservation**.

---

## 1. Expliqué simplement

Deux écrans :

- **La page vitrine** (`/tourisme`) — un bandeau qui rappelle la promesse
  (prestataires vérifiés, programmes détaillés, réservation accompagnée), suivi
  du **catalogue filtrable** (destination, durée, prix, mots-clés). Chaque
  résultat est une carte cliquable qui mène à sa fiche.
- **La fiche d'une expérience** (`/tourisme/:id`) — le programme, la
  destination, la durée, la liste de **ce qui est inclus** (guide, transport,
  restauration…), le **nombre de places restantes**, la note moyenne et les
  avis, puis le **formulaire de réservation** (participants + **date de départ
  seule** : un circuit n'a pas de date de fin, sa durée lui appartient). Depuis
  F8.10 il crée une vraie réservation et emmène le client la régler ; un circuit
  complet n'affiche aucun formulaire, plutôt qu'un formulaire condamné à échouer.
  ⚠️ **Ouvert aux visiteurs non connectés** depuis F8.13 : le bouton (« Se
  connecter pour réserver ») conduit à la connexion et la saisie est
  **conservée** jusqu'au retour (voir
  [`core/state/booking-intent-store.ts`](../../core/state/booking-intent-store.ts)).

---

## 2. Détails techniques

- **`experience-list/`** — `ExperienceListPageComponent`, route `/tourisme`.
  Réutilise `app-catalog` figé sur l'univers `tourisme` ; la mise en page vient
  des styles partagés `.uni-hero` / `.uni-catalog`
  ([`../../../styles/_universe.scss`](../../../styles/_universe.scss)).
- **`experience-detail/`** — `ExperienceDetailPageComponent`, route
  `/tourisme/:id`.
  - Charge en parallèle l'expérience (`CatalogService.experience(id)`), sa
    disponibilité (`experienceAvailability(id)` → **places restantes**) et ses
    avis (`ReviewService.forEntity('experience', id)`) via `forkJoin`. La
    disponibilité et les avis sont **résilients à l'échec** (repli sur vide) ;
    seule l'absence de l'expérience (**404**) bascule en « introuvable ».
    `switchMap` annule la requête précédente d'une fiche à l'autre.
  - **`inclusions` est un objet structuré** `{ guide: true, transport: false,
    … }` (clé → incluse ou non), **pas** un tableau. Le computed
    `inclusionList` ne garde que les inclusions vraies et les affiche avec un
    libellé lisible (le backend peut aussi renvoyer `[]` = aucune inclusion).
  - Le formulaire poste sur `POST /requests` (`service_type = explore`) ; le
    nombre de participants et la date souhaitée sont fusionnés dans le message
    (l'API générique ne porte pas de champ dédié).
  - L'accès au dépôt est gardé par `AuthService.isAuthenticated`.
- Les cartes du catalogue pointent vers la fiche via l'`input [link]` de
  `app-listing-card` (défini dans `catalog.config.ts` : `['/tourisme', id]`).
