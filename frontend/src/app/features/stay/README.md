# `stay/` — Univers Nuitées (F2.3)

> **En une phrase :** tout ce qui permet de **choisir un logement à la nuit**,
> de **vérifier ses disponibilités** et d'en **demander la réservation**.

---

## 1. Expliqué simplement

Deux écrans :

- **La page vitrine** (`/nuitees`) — un bandeau (disponibilités en temps réel,
  caution encadrée, ménage/check-in suivis) puis le **catalogue** des logements
  réservables à la nuit (capacité, prix par nuit, mots-clés).
- **La fiche d'une nuitée** (`/nuitees/:id`) — le logement en détail :
  équipements, règlement intérieur, modalités (capacité, nuits min/max, heures
  d'arrivée/départ, caution), un **calendrier de disponibilité** qui grise les
  jours déjà réservés, les **avis clients**, et un **formulaire de demande de
  réservation** (avec dates d'arrivée/départ). Comme pour l'immobilier, envoyer
  une demande nécessite d'être connecté et renvoie une référence de suivi.

---

## 2. Détails techniques

- **`stay-list/`** — `StayListPageComponent`, route `/nuitees` (réutilise
  `app-catalog` sur l'univers `nuitees`).
- **`stay-detail/`** — `StayDetailPageComponent`, route `/nuitees/:id`.
  - Charge **en parallèle** la nuitée (`CatalogService.stay`), sa disponibilité
    (`CatalogService.stayAvailability`) et ses avis (`ReviewService.forEntity('stay', id)`)
    via `forkJoin`. Disponibilité et avis sont **tolérants à l'échec** (repli sur
    vide) ; seule l'absence de la nuitée bascule en « introuvable ».
  - **Calendrier** : les intervalles réservés sont étalés en un `Set` de jours
    ISO (le jour de départ reste libre → borne de fin exclue). La grille est
    calculée par un `computed` (semaines lundi→dimanche), navigable de mois en
    mois sans revenir dans le passé.
  - Le formulaire poste sur `POST /requests` (`service_type = stay`) avec les
    dates fusionnées dans le message. La **réservation ferme** (paiement +
    caution) relève d'une phase ultérieure ; ici l'utilisateur exprime son
    besoin, qu'un conseiller confirme.
- Styles spécifiques (chips d'équipements, calendrier, avis) dans
  `stay-detail-page.scss` ; l'ossature générale vient de `.uni-detail-*`
  ([`../../../styles/_universe.scss`](../../../styles/_universe.scss)).
