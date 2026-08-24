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
  renvoie vers l'espace diaspora (`/espace-diaspora`) pour lancer un dossier
  structuré une fois connecté.

---

## 3. Espace diaspora connecté (F3.8, indépendant depuis F18)

⚠️ **Espace autonome depuis le 2026-08-22** (F18) — auparavant nichés dans
l'espace client (`/mon-espace/diaspora`), ces écrans vivent désormais sous
`/espace-diaspora`, gardés par leur **propre rôle** Spatie `diaspora`
(`UserRole::DIASPORA`), au même titre que propriétaire/prestataire/entreprise.
Un compte diaspora n'a plus accès à `/mon-espace` (séparation complète — voir
`diaspora.routes.ts` + `diaspora-space.ts`). Ils comblent l'exigence CDC §15
(« un dossier diaspora peut être **créé, suivi et enrichi de rapports** »).

- **`diaspora-projects/diaspora-projects-page`** (`''`, accueil de l'espace) —
  liste **Mes projets diaspora** (`GET /diaspora-projects/mine`) : type, pays de
  résidence, budget, **nombre de rapports** et **statut** (pastille
  `.dp-badge`). Bouton de lancement + accès au détail.
- **`diaspora-projects/diaspora-project-form-page`** (`nouveau`) — lancement
  d'un projet (`POST /diaspora-projects`) : type (achat / construction /
  gestion locative), pays de résidence, budget et priorité. Miroir de
  `StoreDiasporaProjectRequest` ; à la création on ouvre directement le détail.
- **`diaspora-projects/diaspora-project-detail-page`** (`:id`) — détail du
  projet **+ chronologie des rapports** (`GET /diaspora-projects/{id}` et
  `/reports`, chargés en parallèle). Chaque rapport affiche type, date,
  commentaire, **galerie photos** et **lien vidéo**. Lecture seule : les rapports
  sont déposés par l'**agent affecté** (back-office). Le backend renvoie 404 si le
  projet n'est pas au client (isolation par policy) → état « introuvable ».
- Service **`core/api/diaspora.service.ts`** (`DiasporaService`) : constantes
  `DIASPORA_PROJECT_TYPES` / `DIASPORA_PRIORITIES`, méthodes `myProjects` /
  `createProject` / `project` / `reports`. Modèle `models/diaspora.model.ts`
  (`DiasporaProject`, `DiasporaReport`).

---

## F3.9 — « Mes chantiers & devis » (réponse du client à un devis de chantier)

⚠️ **Ce bloc a QUITTÉ cet écran lors de la séparation F18** (2026-08-22). Il
vit désormais sur l'accueil de l'espace **CLIENT**
(`features/account/overview/account-overview-page`), pas ici : le composant
**`shared/components/construction-quotes/`** (`app-construction-quotes`)
s'adresse à **tous** les clients (rattachement par client, pas par projet
diaspora), diaspora ou non — l'y laisser aurait privé tout client résident de
son seul moyen de répondre à un devis de chantier, puisque l'espace diaspora
lui est désormais fermé.

**⚠️ Rattachement par CLIENT, pas par projet.** `diaspora_projects` n'a **aucune
clé étrangère** vers `construction_requests` : ce sont deux dossiers parallèles
du même client. Le bloc liste donc les chantiers du client (`GET
/construction-requests/mine`), pas ceux « du projet diaspora ouvert ». Un client
à deux chantiers voit ses deux devis dans la même section, chacun identifié par
sa référence de chantier. Lier réellement les deux (migration + écran de
rattachement au back-office) reste possible ; le composant se déplacerait sans
être réécrit.

**Ce que fait le composant.** Il est **autonome** : il charge lui-même ses
données, avec son propre état de chargement et d'erreur, et il est placé **hors
du `@switch`** de la page — un incident sur les projets diaspora ne doit pas
empêcher de répondre à un devis. Il se **masque entièrement** quand le client n'a
aucun chantier.

**Partis pris d'interface**

- **Confirmation en deux temps** avant d'accepter comme de refuser. Accepter
  engage des millions de francs : un clic malheureux sur un téléphone ne doit pas
  suffire. Refuser passe par la même étape — un refus accidentel enverrait
  l'équipe refaire un chiffrage pour rien.
- **Le montant domine** la carte (c'est ce sur quoi le client s'engage) ; le
  **détail des lots est replié** par défaut, disponible pour qui vérifie.
- **La validité est signalée, jamais bloquante** : un devis dépassé affiche un
  avertissement mais garde ses boutons. C'est le **serveur** qui décide de ce qui
  est acceptable, pas l'horloge du téléphone — un appareil mal réglé ne doit pas
  priver quelqu'un de sa décision.
- Un **422** (devis tranché ou renvoyé entre-temps) **recharge la liste** au lieu
  de laisser un bouton mort.

**Côté serveur** — `ConstructionService.mine()` renvoie les chantiers **devis
inclus** (un seul appel ; sinon ce serait un appel HTTP par chantier), les
**brouillons étant exclus côté serveur**. Réponses : `acceptQuote()` /
`refuseQuote()`, réservées au client par la policy `respond`.
