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
- **La fiche d'une expérience** (`/tourisme/:id`) — le programme jour par jour,
  la destination, la durée, ce qui est **compris / non compris** (texte libre,
  F21), puis le **formulaire de réservation** (participants + **une date de
  départ choisie parmi celles proposées**, chacune avec ses propres places : un
  circuit n'a pas de date de fin, sa durée lui appartient). Depuis F8.10 il crée
  une vraie réservation et emmène le client la régler ; un circuit sans aucune
  date programmée, ou complet sur toutes ses dates, n'affiche aucun formulaire,
  plutôt qu'un formulaire condamné à échouer.
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
    disponibilité (`experienceAvailability(id)` → **dates de départ à venir,
    chacune avec ses places restantes**, F21) et ses avis
    (`ReviewService.forEntity('experience', id)`) via `forkJoin`. La
    disponibilité et les avis sont **résilients à l'échec** (repli sur vide) ;
    seule l'absence de l'expérience (**404**) bascule en « introuvable ».
    `switchMap` annule la requête précédente d'une fiche à l'autre.
  - **Le client choisit un `departure_id`, pas une date libre** (F21) : le
    `<select>` liste les dates programmées par le prestataire/admin, désactive
    celles déjà complètes, et `canQuote`/`bookingHint` lisent les places
    restantes de la date **sélectionnée** (`selectedDeparture`), pas un total
    global.
  - Le formulaire poste sur `POST /experiences/{id}/bookings`
    (`BookingService.createExperienceBooking`) — une vraie réservation, places
    décomptées par date, pas une demande générique.
  - L'accès au dépôt est ouvert aux visiteurs non connectés (F8.13) : la
    connexion n'est demandée qu'au clic, `BookingIntentStore` conserve la
    saisie (`departure_id` + `seats`) le temps de l'aller-retour.
- Les cartes du catalogue pointent vers la fiche via l'`input [link]` de
  `app-listing-card` (défini dans `catalog.config.ts` : `['/tourisme', id]`).
