# `build/` — Univers Construction (F2.5)

> **En une phrase :** la page qui donne envie de **construire ou rénover au
> Sénégal avec Kaikun 360**, avec un **simulateur de budget** qui chiffre le
> projet en direct.

---

## 1. Expliqué simplement

Une seule page (`/construction`) : le bandeau de promesse (artisans vérifiés,
suivi filmé et daté, paiements par jalons), un rappel « comment ça marche » en
trois étapes, puis le **simulateur**. Le visiteur choisit le type de projet
(construction neuve, extension, rénovation), fait glisser la surface et choisit
un niveau de finition — l'**estimation en FCFA se met à jour instantanément**.
À côté, un formulaire de demande de devis est **pré-rempli avec cette
estimation** : il suffit de compléter et d'envoyer (connexion requise).

L'estimation est **indicative** : le devis ferme est établi par un professionnel
après étude du projet.

---

## 2. Détails techniques

- **`construction-page/`** — `ConstructionPageComponent`, route `/construction`.
  Objectif / niveau de finition sont des signaux (`objective`, `finish`), la
  surface aussi (`surface`) ; l'estimation est un `computed` recalculé à chaque
  changement. Un `computed` `leadMessage` synchronise le message pré-rempli du
  formulaire avec les paramètres du simulateur.
- **`construction-estimator.ts`** — **miroir fidèle** du calcul backend
  `App\Modules\Build\Services\ConstructionEstimator` (B5.4) : mêmes tarifs de
  base au m² (neuve 250 000 / extension 220 000 / rénovation 150 000),
  coefficients de finition (éco 0,85 / standard 1,0 / premium 1,35) et arrondi
  (pas de 100 000). On duplique côté client parce que l'endpoint
  `POST /construction-requests/simulate` **exige une session**, alors que la
  page est publique. ⚠️ **Garder aligné sur le backend** si les tarifs changent.
- Le formulaire de devis est le composant partagé
  [`app-lead-form`](../../shared/components/lead-form) (`service_type = build`,
  champs ville + budget affichés).
- Styles : bandeau `.uni-hero` + sections `.conv-*` partagés ; le simulateur a
  ses styles propres (`build-sim-*`, `build-choice`, `build-range`,
  `build-result`) dans `construction-page.scss`.
