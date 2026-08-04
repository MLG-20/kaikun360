# `mobility/` — Univers Transport & Mobilité (F2.4)

> **En une phrase :** louer un **véhicule vérifié** (avec ou sans chauffeur) et
> trouver une **navette / un transfert**, et les **réserver fermement** (F8.10).

---

## 1. Expliqué simplement

Deux univers proches, regroupés ici car ils viennent du même module backend
(*Mobility*) :

- **Transport** — la location de véhicules.
  - **La page vitrine** (`/transport`) : bandeau + **catalogue filtrable**
    (type, places, prix, avec chauffeur). Chaque carte mène à sa fiche.
  - **La fiche d'un véhicule** (`/transport/:id`) : caractéristiques (type,
    marque/modèle, places, chauffeur, **caution**), note moyenne et avis, puis
    le **formulaire de location ferme** (dates de début/fin, passagers). ⚠️ Les
    deux bornes sont **incluses** et une location d'un seul jour est permise :
    rendre le véhicule le jour même n'annule pas la mise à disposition.
- **Mobilité** — les navettes, transferts et excursions.
  - **La page vitrine** (`/mobilite`) : bandeau + **catalogue filtrable**
    (départ, destination, date), et **la fiche d'un départ** (`/mobilite/:id`),
    créée en F8.10 en même temps que le `GET /mobility-services/{id}` qui lui
    manquait. On n'y choisit **que le nombre de places** : le trajet est déjà
    daté, proposer une date laisserait croire qu'on peut en changer.

⚠️ **Réservation ferme sur les deux, depuis F8.10** : les fiches créaient une
simple *demande* qu'un conseiller relisait à la main. Et depuis **F8.13**, leurs
formulaires sont **ouverts aux visiteurs non connectés** — le bouton conduit à la
connexion, la saisie est conservée jusqu'au retour (voir
[`core/state/booking-intent-store.ts`](../../core/state/booking-intent-store.ts)).

Un renvoi croisé relie les deux vitrines (Transport ↔ Mobilité).

---

## 2. Détails techniques

- **`vehicle-list/`** — `VehicleListPageComponent`, route `/transport`.
  `app-catalog` figé sur l'univers `transport`, + un renvoi `routerLink` vers
  `/mobilite`.
- **`vehicle-detail/`** — `VehicleDetailPageComponent`, route `/transport/:id`.
  - Charge le véhicule (`CatalogService.vehicle(id)`, **404** = introuvable)
    puis ses avis (`ReviewService.forEntity('vehicle', id)`, résilient à
    l'échec). `switchMap` annule la requête précédente d'une fiche à l'autre.
  - Le formulaire poste sur `POST /vehicles/{id}/bookings` (F8.10) puis redirige
    vers `/mon-espace/reservations/:id/paiement`. ⚠️ Le contrôle de
    **chevauchement** a été ajouté côté serveur au passage : la double-location
    était possible.
- **`mobility-list/`** — `MobilityListPageComponent`, route `/mobilite`.
  `app-catalog` figé sur l'univers `mobilite` (recherche départ/destination/
  date), + un renvoi vers `/transport`.
- **`trip-detail/`** — `TripDetailPageComponent`, route `/mobilite/:id` (F8.10).
  Poste sur `POST /mobility-services/{id}/bookings` ; `seats_left` est **servi
  par le serveur**, un départ passé ou complet n'affiche pas de formulaire.
- Cartes du catalogue : `['/transport', id]` pour les véhicules,
  `['/mobilite', id]` pour les départs (défini dans `catalog.config.ts` — elles
  étaient `null`, donc non cliquables, tant que la fiche n'existait pas).
- Structure et éléments transverses (avis, chips, caution) = styles partagés
  `.uni-*` ([`../../../styles/_universe.scss`](../../../styles/_universe.scss)).
