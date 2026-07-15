# `build/` — Univers Construction (F2.5, enrichi « réalités sénégalaises »)

> **En une phrase :** la page qui donne envie de **construire ou rénover au
> Sénégal avec Kaikun 360**, avec un **simulateur complet** (calculé par le
> backend) qui chiffre le projet, le décompose, l'échelonne et projette sa
> rentabilité en direct.

---

## 1. Expliqué simplement

Une seule page (`/construction`) : le bandeau de promesse (artisans vérifiés,
suivi filmé et daté, paiements par jalons), un rappel « comment ça marche » en
trois étapes, puis le **simulateur**, présenté comme dans le prototype :
**un formulaire compact à gauche, un panneau de résultat unique à droite**.

Dans le formulaire, le visiteur décrit son projet — type de travaux, **surface
au sol** (à saisir, avec curseur), **nombre de niveaux** (plain-pied / R+1 /
R+2), **finition**, **lieu** (menu déroulant des 14 régions → le simulateur en
déduit la zone de coût), **foncier** (terrain déjà possédé ou à acquérir) et,
optionnellement, **son budget disponible**.

Le **panneau de résultat** regroupe tout au même endroit (pas de cartes
éparpillées) : le **budget total du projet**, un **verdict de faisabilité** (« votre
budget couvre le projet » / « il manque X »), les **métriques clés** (travaux,
frais annexes, terrain, délai, surface totale), puis des **sections repliables**
à l'intérieur du panneau : répartition des travaux, **frais annexes officiels**
(études, permis, viabilisation SENELEC/eau), échéancier par jalons, et projection
de **rentabilité locative**. Sous le simulateur, un formulaire de demande de devis
est **pré-rempli avec l'estimation** (connexion requise).

Quand le terrain est **à acquérir**, un rappel invite à **vérifier le titre
foncier** — cœur du protocole anti-arnaque, surtout pour la diaspora.

L'estimation est **indicative** : le devis ferme est établi par un professionnel
après étude du projet.

---

## 2. Détails techniques

- **🔑 Le calcul vit ENTIÈREMENT côté backend** (`ConstructionEstimator`, source
  unique dont le barème est géré au back-office via le réglage `build.pricing`).
  Le frontend **ne duplique plus aucun tarif** : il collecte les paramètres et
  affiche le détail renvoyé. C'est ce qui garantit que les chiffres restent
  cohérents et pilotables par l'équipe (experts BTP) sans toucher au code.
- **`construction-page/`** — `ConstructionPageComponent`, route `/construction`.
  Les paramètres sont des signaux (`objective`, `surface`, `levels`, `finish`,
  `zone`, `landOwned`, `landCost`) ; un `computed` `payload` les agrège, un flux
  RxJS **débouncé** (`toObservable` → `debounceTime(250)` → `switchMap` →
  `ConstructionService.simulate` → `toSignal`) appelle le backend et expose un
  état `loading` / `ready` / `error`. Le `computed` `leadMessage` synchronise le
  message pré-rempli du formulaire avec les paramètres **et** le résultat backend.
  Le `zone()` est un **computed** dérivé de la région choisie (`region` signal) ;
  le `budgetVerdict()` compare le budget saisi au coût total renvoyé.
- **`core/api/construction.service.ts`** (`ConstructionService`) — appelle
  `POST /construction-requests/simulate` (**public** depuis cet enrichissement) et
  porte les types miroir de la réponse (`Simulation`, `CostShare`,
  `RentalProjection`) + les libellés d'IHM et **`SENEGAL_REGIONS`** (les 14 régions
  rattachées chacune à une zone de coût — regroupement d'IHM ; les coefficients de
  zone restent gérés au backend). *(L'ancien `construction-estimator.ts`, qui
  dupliquait le calcul en local, a été supprimé.)*
- Le formulaire de devis est le composant partagé
  [`app-lead-form`](../../shared/components/lead-form) (`service_type = build`,
  champs ville + budget affichés).
- Styles : bandeau `.uni-hero` + sections `.conv-*` partagés ; le simulateur a ses
  styles propres dans `construction-page.scss` — formulaire clair `.build-sim-form`
  (+ `.build-choice`, `.build-input`, `.build-select`, `.build-range`) et **panneau
  de résultat unique navy** `.build-result` (+ `.build-verdict`, `.build-metrics`,
  accordéons `.build-acc`, et les blocs de détail `.build-bars`/`.build-fees`/
  `.build-steps-pay`/`.build-yield` restylés pour le fond foncé).
