# `mobility/` — Univers Transport & Mobilité (F2.4)

> **En une phrase :** louer un **véhicule vérifié** (avec ou sans chauffeur) et
> trouver une **navette / un transfert**, avec demande de réservation.

---

## 1. Expliqué simplement

Deux univers proches, regroupés ici car ils viennent du même module backend
(*Mobility*) :

- **Transport** — la location de véhicules.
  - **La page vitrine** (`/transport`) : bandeau + **catalogue filtrable**
    (type, places, prix, avec chauffeur). Chaque carte mène à sa fiche.
  - **La fiche d'un véhicule** (`/transport/:id`) : caractéristiques (type,
    marque/modèle, places, chauffeur, **caution**), note moyenne et avis, puis
    un **formulaire « Demander une réservation »** (dates de début/fin).
- **Mobilité** — les navettes, transferts et excursions.
  - **La page vitrine** (`/mobilite`) : bandeau + **catalogue filtrable**
    (départ, destination, date). Le backend n'expose **pas** de fiche détaillée
    pour un service de mobilité (index + réservation seulement) : les cartes ne
    sont donc **pas cliquables**, et la réservation passe par un conseiller.

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
  - Le formulaire poste sur `POST /requests` (`service_type = mobility`) ; les
    dates souhaitées sont fusionnées dans le message.
- **`mobility-list/`** — `MobilityListPageComponent`, route `/mobilite`.
  `app-catalog` figé sur l'univers `mobilite` (recherche départ/destination/
  date), + un renvoi vers `/transport`. **Vitrine seule** — pas de fiche.
- Cartes du catalogue : `['/transport', id]` pour les véhicules ; **`null`**
  (non cliquable) pour la mobilité (défini dans `catalog.config.ts`).
- Structure et éléments transverses (avis, chips, caution) = styles partagés
  `.uni-*` ([`../../../styles/_universe.scss`](../../../styles/_universe.scss)).
