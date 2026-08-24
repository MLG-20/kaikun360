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
de **rentabilité locative**. Sous le simulateur, un formulaire **ouvre un vrai
dossier de chantier** (`CST-…`), pré-rempli avec l'estimation et reprenant les
paramètres déjà réglés au-dessus (connexion requise). Le client le retrouve dans
son espace, l'équipe le reçoit dans son écran « Construction ».

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
- **`construction-request-form/`** — `ConstructionRequestFormComponent`, le
  formulaire sous le simulateur. ⚠️ **Il a remplacé `app-lead-form` en F8.15.b.**
  La page envoyait jusque-là un `POST /requests` **générique** dont le corps était
  le simulateur résumé en TEXTE : la demande atterrissait dans `requests` et
  n'atteignait **jamais** l'écran back-office « Construction », qui lit
  `construction_requests`. `POST /construction-requests` existait depuis B5.5 et
  **n'avait aucun appelant** — donc aucun jalon semé, aucune estimation
  enregistrée, aucun devis ventilé par lot composable : tout l'aval du module
  (rapports photo/vidéo, acceptation du devis par le client en F3.9, conversion en
  réservation payable en F8.14) partait d'une porte murée.
  ⚠️ **Aucune ressaisie** : objectif, ville, surface, finition et budget viennent
  des signaux du simulateur, que le visiteur a déjà réglés. Le formulaire ne porte
  qu'un champ — la description, pré-remplie du récapitulatif (qui garde ce
  qu'aucune colonne ne stocke : niveaux, foncier, estimation affichée, projection
  locative). Il réutilise les classes `.k-card.lead-form` pour que la page ne
  change pas d'aspect. ⚠️ **Et pendant un temps, elle en a changé** : ces classes
  vivaient dans `lead-form.scss`, la feuille du composant `app-lead-form`. Sous
  encapsulation émulée, une feuille de composant ne touche que les éléments écrits
  dans SON gabarit — les mêmes classes, réécrites ici, n'étaient donc habillées
  par rien, et la carte s'affichait **sans marge intérieure ni espacement des
  champs**, texte collé aux bords, *sans qu'aucune erreur ne le signale*. Elles
  sont désormais dans `styles/_conversion.scss` (global). La règle : une classe
  partagée par deux composants est un style global, sinon elle ne vaut que pour le
  premier. ⚠️ Dans la même carte, `.k-hint` était employée **sans avoir jamais été
  définie** nulle part — un sélecteur absent ne lève rien, l'explication sous le
  champ s'affichait en corps de page ; elle est maintenant dans `_base.scss`, aux
  côtés de `.k-error`. Le succès nomme le dossier (`CST-…`) et **dit où le
  suivre** (`/mon-espace`, « Mes chantiers & devis ») : un chantier a une
  vie, contrairement à une demande de rappel. ⚠️ Depuis la séparation de
  l'espace diaspora (F18, 2026-08-22), ce bloc vit sur l'accueil de l'espace
  CLIENT (il s'adresse à tout client, pas aux seuls comptes diaspora) — plus
  sous `/mon-espace/diaspora`, qui a quitté l'espace client.
  ⚠️ Côté serveur, le dépôt **ne prévenait personne** — `ConstructionRequestCreated`
  + `NotifyAdminsOfConstructionRequest` ont été ajoutés en même temps (le manque
  était masqué par la demande générique, alertée depuis B11.2).
- Styles : bandeau `.uni-hero` + sections `.conv-*` partagés ; le simulateur a ses
  styles propres dans `construction-page.scss` — formulaire clair `.build-sim-form`
  (+ `.build-choice`, `.build-input`, `.build-select`, `.build-range`) et **panneau
  de résultat unique navy** `.build-result` (+ `.build-verdict`, `.build-metrics`,
  accordéons `.build-acc`, et les blocs de détail `.build-bars`/`.build-fees`/
  `.build-steps-pay`/`.build-yield` restylés pour le fond foncé).
