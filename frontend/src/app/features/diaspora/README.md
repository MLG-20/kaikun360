# `diaspora/` — Univers Diaspora (F2.5) + projets diaspora connectés (F3.8)

> **En une phrase :** la page qui rassure un Sénégalais de l'étranger sur le fait
> qu'il peut **piloter un projet au pays sans se faire arnaquer** (F2.5), et les
> écrans connectés qui lui permettent de **lancer et suivre ce projet, rapports
> à l'appui** (F3.8).

---

## 1. Expliqué simplement

Une page (`/diaspora`) construite autour du **protocole de confiance** — le cœur
du positionnement anti-arnaque de Kaikun 360 : vérification documentée, tout est
filmé et daté, numéro de suivi unique. Elle explique le principe du **référent
unique** qui coordonne tout sur place, décline les bénéfices, puis propose un
**formulaire de contact** pour être accompagné depuis l'étranger.

---

## 2. Détails techniques

- **`diaspora-page/`** — `DiasporaPageComponent`, route `/diaspora`. Page de
  présentation + le formulaire partagé
  [`app-lead-form`](../../shared/components/lead-form) (`service_type = diaspora`,
  champ ville affiché).
- Le bandeau « protocole de confiance » sur fond navy a des styles **propres**
  (`diaspora-trust-*` dans `diaspora-page.scss`) ; le reste réutilise `.uni-hero`
  et les sections `.conv-*`. Un lien `.conv-account-link` sous le formulaire
  renvoie vers l'espace client (`/mon-espace/diaspora`) pour lancer un dossier
  structuré une fois connecté.

---

## 3. Projets diaspora connectés (F3.8)

Écrans montés **dans l'espace client** (`/mon-espace/diaspora`, routes déclarées
dans [`account.routes.ts`](../account/account.routes.ts)), qui comblent
l'exigence CDC §15 (« un dossier diaspora peut être **créé, suivi et enrichi de
rapports** ») restée sans interface — le backend l'exposait déjà.

- **`diaspora-projects/diaspora-projects-page`** (`diaspora`) — liste **Mes
  projets diaspora** (`GET /diaspora-projects/mine`) : type, pays de résidence,
  budget, **nombre de rapports** et **statut** (pastille `.dp-badge`). Bouton de
  lancement + accès au détail.
- **`diaspora-projects/diaspora-project-form-page`** (`diaspora/nouveau`) —
  lancement d'un projet (`POST /diaspora-projects`) : type (achat / construction
  / gestion locative), pays de résidence, budget et priorité. Miroir de
  `StoreDiasporaProjectRequest` ; à la création on ouvre directement le détail.
- **`diaspora-projects/diaspora-project-detail-page`** (`diaspora/:id`) — détail
  du projet **+ chronologie des rapports** (`GET /diaspora-projects/{id}` et
  `/reports`, chargés en parallèle). Chaque rapport affiche type, date,
  commentaire, **galerie photos** et **lien vidéo**. Lecture seule : les rapports
  sont déposés par l'**agent affecté** (back-office). Le backend renvoie 404 si le
  projet n'est pas au client (isolation par policy) → état « introuvable ».
- Service **`core/api/diaspora.service.ts`** (`DiasporaService`) : constantes
  `DIASPORA_PROJECT_TYPES` / `DIASPORA_PRIORITIES`, méthodes `myProjects` /
  `createProject` / `project` / `reports`. Modèle `models/diaspora.model.ts`
  (`DiasporaProject`, `DiasporaReport`).
