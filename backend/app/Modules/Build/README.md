# Module Build — Construction / rénovation / devis

Un client décrit un projet de **construction**, **rénovation** ou **extension** ;
Kaikun l'étudie, propose un devis (couche transversale Quotes, phase B11) et
assure le **suivi de chantier** (rapports photo/vidéo, jalons).

---

## Demandes de construction (phase B5.1)

### Table `construction_requests`

| Champ | Rôle |
|---|---|
| `reference` (unique) | identifiant lisible de la demande (`CST-…`) |
| `client_id` | client à l'origine de la demande |
| `objective` | nature du projet — enum `ConstructionObjective` |
| `city` | ville du projet |
| `surface_m2` | surface visée (m²) |
| `budget_xof` | budget annoncé par le client (facultatif) |
| `finish_level` | niveau de finition — enum `FinishLevel` |
| `description` | descriptif libre |
| `estimated_cost_xof` | estimation indicative du simulateur (B5.4) |
| `status` | avancement — enum `ConstructionRequestStatus` (défaut `soumise`) |

### Modèle `ConstructionRequest` (`app/Modules/Build/Models/`)

- `belongsTo` client (User).
- Casts : enums `objective`/`finish_level`/`status` + entiers (`surface_m2`,
  `budget_xof`, `estimated_cost_xof`).
- Recevra `reports()` (B5.2), `milestones()` (B5.3) et les devis via Quotes (B11).

### Enums (`app/Modules/Build/Enums/`)

- `ConstructionObjective` : `construction_neuve`, `renovation`, `extension`.
- `FinishLevel` : `economique`, `standard`, `premium` (coefficient simulateur).
- `ConstructionRequestStatus` : `soumise` → `en_etude` → `devis_envoye` →
  `acceptee` → `en_chantier` → `terminee` (+ `annulee`).

---

## Rapports de suivi de chantier (phase B5.2)

### Table `reports` (polymorphe)

| Champ | Rôle |
|---|---|
| `reference` (unique) | identifiant lisible (`RPT-…`) |
| `reportable_type` / `reportable_id` | cible polymorphe (Construction ou Diaspora B8) |
| `created_by` | auteur du rapport (agent de suivi), facultatif |
| `type` | enum `ReportType` : `photo`, `video`, `mixte` |
| `photos` (json) | liste de chemins (disque privé) |
| `video_url` | URL de la vidéo éventuelle |
| `comment` | commentaire de chantier |
| `reported_at` | date du constat |

### Modèle transversal `App\Models\Report`

- `morphTo` `reportable` (suit la convention du modèle transversal `Booking`).
- `belongsTo` author (User, `created_by`).
- Casts : `type` enum, `photos` en tableau, `reported_at` date.
- Côté Build : `ConstructionRequest::reports()` (`morphMany`).

---

## Jalons de chantier (phase B5.3)

### Table `construction_milestones`

| Champ | Rôle |
|---|---|
| `construction_request_id` | demande rattachée |
| `name` | libellé de l'étape |
| `position` | ordre d'exécution |
| `status` | enum `MilestoneStatus` : `a_venir`, `en_cours`, `termine` |
| `planned_date` / `actual_date` | dates prévisionnelle et réelle |

### Modèle & relation

- `ConstructionMilestone` : `belongsTo` constructionRequest, casts dates/enum.
- `ConstructionRequest::milestones()` (`hasMany`, ordonné par `position`).

### Service `ConstructionMilestoneService`

- `defaultStagesFor(objective)` : découpage type (le parcours « neuf »/« extension »
  comporte fondations + toiture ; la « rénovation » commence par un diagnostic).
- `seedDefault(request)` : matérialise les jalons par défaut (statut `a_venir`),
  **idempotent** (ne duplique pas si des jalons existent déjà).

---

## Simulateur de budget (phase B5.4, enrichi « réalités sénégalaises »)

Service `ConstructionEstimator` :

- `estimate(objective, surfaceM2, finishLevel)` → coût des **travaux** seuls (XOF),
  au niveau RDC / zone Dakar (signature historique conservée pour le dépôt de
  demande).
- `breakdown(objective, surfaceM2, finishLevel, levels = 1, zone = dakar, landCostXof = 0)`
  → **détail complet** consommé par le frontend :
  - `works` : coût des travaux + répartition (gros œuvre / second œuvre /
    finitions) + échéancier par jalons ;
  - `fees` : frais annexes officiels (études & honoraires, permis, viabilisation) ;
  - `land` : foncier (le prix du terrain est **saisi**, jamais deviné) + frais
    d'acquisition (notaire/bornage/enregistrement) ;
  - `grand_total_xof`, `duration` (délai en mois), `rental` (rendement locatif
    indicatif longue durée / nuitée).

**Formule des travaux** : `prix_m² × (surface au sol × niveaux) × coeff finition ×
coeff zone`, arrondi au pas.

### 🔑 Barème piloté par les réglages (`build.pricing`)

Tous les **coefficients monétaires** (prix au m², finitions, zones, taux de frais,
taux d'acquisition foncière, rendements) vivent dans le réglage **`build.pricing`**
({@see `App\Support\SettingsRepository::DEFAULTS`}, type `json`, groupe
`construction`). Les valeurs du code sont des **ordres de grandeur par défaut** ;
l'**équipe admin les remplace par de vrais chiffres d'experts BTP** via le
back-office (`PATCH /admin/settings`), **sans redéploiement**. Une surcharge
**partielle** est acceptée (fusion récursive sur les défauts). L'estimateur est
donc la **source unique** du calcul, alignée en direct sur les réglages.

La répartition des travaux, l'échéancier et le délai sont **structurels**
(méthodologie) et restent en code.

> ⚠️ Estimation **non contractuelle** ; le devis ferme relève des Quotes (B11).
> Le endpoint `POST /construction-requests/simulate` est **public** (pur calcul,
> aucune donnée personnelle) pour alimenter la page Construction du site.

---

## Endpoints & policy (phase B5.5)

### Espace client (auth)

| Méthode | URL | Effet |
|---|---|---|
| POST | `/api/v1/construction-requests` | dépose une demande (estimation auto + jalons par défaut, statut `soumise`) |
| POST | `/api/v1/construction-requests/simulate` | simulation de budget (sans persistance) |
| GET | `/api/v1/construction-requests/mine` | mes demandes (paginées, `reports_count`) |
| GET | `/api/v1/construction-requests/{id}` | détail + jalons (policy `view`) |
| GET | `/api/v1/construction-requests/{id}/reports` | rapports de suivi (policy `view`) |

### Suivi de chantier (agents, permission `gerer:chantiers`)

| Méthode | URL | Effet |
|---|---|---|
| POST | `/api/v1/construction-requests/{id}/reports` | publie un rapport (`created_by` = agent) |
| POST | `/api/v1/construction-requests/{id}/milestones` | ajoute un jalon (F7.3.e1 ; `position` omise = fin de planning) |
| PUT | `/api/v1/construction-requests/{id}/milestones/reorder` | réécrit l'ordre du planning (liste ordonnée d'identifiants) |
| PATCH | `/api/v1/construction-milestones/{id}` | fait avancer / replanifie un jalon |
| DELETE | `/api/v1/construction-milestones/{id}` | retire un jalon du planning |

- **Policy** `ConstructionRequestPolicy` : `create` = tout authentifié ; `view` =
  client propriétaire **ou** agent/admin (super_admin via Gate::before). Enregistrée
  dans `AppServiceProvider`.
- **Permission** `gerer:chantiers` ajoutée au rôle agent (+ admin) dans le seeder.
- Resources : `ConstructionRequestResource` (jalons `whenLoaded`, `reports_count`
  `whenCounted`), `ConstructionMilestoneResource`, `ReportResource`.
- À la création : `ConstructionEstimator` renseigne `estimated_cost_xof` et
  `ConstructionMilestoneService` sème les jalons.

## Fiche dossier au back-office (F7.3.b)

L'écran back-office *Dossiers* (F7.2.e) ne montrait les demandes que sous forme
de tableau — illisible pour un dossier de chantier, dont l'essentiel (qui a
demandé quoi, où en est le chantier, ce qui a été constaté sur place) ne tient
pas dans une ligne. Une **fiche** consomme désormais les endpoints existants :

- `GET /construction-requests/{id}` — policy `view` (client propriétaire **ou**
  agent/admin). Charge le **client**, les **jalons triés par `position`** (l'ordre
  du chantier est ce que l'écran restitue) et le compte des rapports.
- `GET|POST /construction-requests/{id}/reports` — comptes rendus photo/vidéo ;
  la publication exige `gerer:chantiers`.

**Deux ajouts additifs** dans `ConstructionRequestResource`, sans migration :

- **`client`** (id, nom, e-mail, téléphone) via `whenLoaded` — le back-office
  pilote des dossiers, pas des lignes anonymes : sans le demandeur, l'agent ne
  peut ni comprendre ni rappeler. Même correctif qu'en F7.2.a sur la file de
  validation. `AdminDossierController::constructionRequests` eager-load donc
  `client` (anti-N+1), ce qui alimente aussi une colonne **Demandeur** dans la
  liste.
- **`created_at`** — un dossier de suivi se lit d'abord par son ancienneté.

## Pilotage des jalons (F7.3.e1)

Les jalons étaient semés au dépôt (`ConstructionMilestoneService::seedDefault()`)
puis **figés** : aucun endpoint ne les touchait, alors que « jalons chantier » est
une fonction explicite du CDC §6. `ConstructionMilestoneController` comble ce trou
et sert les deux gestes du terrain — *faire avancer* (démarrer, achever, rouvrir)
et *replanifier* (ajouter, renommer, redater, réordonner, retirer), car aucun
chantier ne suit exactement le gabarit posé à la création.

Trois règles portées par le serveur, à ne pas dupliquer côté client :

- **Cohérence statut ↔ date réelle.** Passage à `termine` sans `actual_date` → date
  du jour ; retour à `a_venir`/`en_cours` → `actual_date` **effacée**. Sans cela un
  jalon rouvert resterait « en cours, achevé le … ».
- **Réordonnancement par liste ordonnée d'identifiants**, en transaction, positions
  réécrites `1..n`. Envoyer une position par jalon produirait un doublon transitoire
  et un ordre indéterminé si la seconde écriture échouait.
- **Ordre refusé en bloc (422)** si un identifiant n'appartient pas au chantier :
  un planning à moitié réordonné est pire qu'un refus.

Un `PATCH` sans aucun champ est refusé (422) plutôt que de renvoyer un succès qui
n'a rien changé. La suppression **ne recompacte pas** les positions restantes :
l'affichage trie par `position`, un trou est invisible, et recompacter obligerait à
réécrire toute la liste à chaque retrait.

Tests : `tests/Feature/Build/ConstructionMilestonePilotageTest.php` (9 cas — dont
refus au client, effacement de la date à la réouverture, ordre étranger refusé).
Aucune migration : la table `construction_milestones` existe depuis B5.3.

## Devis de chantier (F7.3.e2)

Le CDC §6 attend des « demandes de devis ». La plateforme ne produisait qu'un
**coût estimé** par le simulateur — utile pour cadrer un projet, mais ce n'est pas
un devis : pas de ventilation par lot, aucun engagement, aucun cycle d'acceptation.

⚠️ **Correction d'un audit antérieur** : `ConstructionRequest::quotes` n'existait
pas et la table transversale `quotes` (B11.3) **ne pouvait pas servir** — elle pend
sur `requests` (demandes de contact génériques) et ne porte qu'un montant global.
Le devis de chantier a donc sa table, `construction_quotes`, sur le motif éprouvé
des devis pack du team building (`team_building_quotes`, B9.2).

- **Lignes ventilées par lot** (`ConstructionLot` : études, terrassement,
  fondations, gros œuvre, charpente, menuiserie, plomberie, électricité, finitions,
  extérieurs, main d'œuvre, divers) avec unité (m², m³, ml, u, forfait, jour) et
  **quantité décimale** — 18,5 m³ de semelles se chiffrent, contrairement au team
  building qui compte des participants entiers.
- **Lignes triées à la composition dans l'ordre d'exécution** du chantier (ordre des
  cas de l'enum) : un devis présenté dans l'ordre de saisie est illisible. Le tri
  est fait une fois, puisque les lignes sont ensuite figées.
- **Totaux FIGÉS** (sous-total, marge, total). Un devis envoyé ne doit plus bouger :
  si le barème ou la marge change après coup, le document que le client a reçu reste
  celui qu'il a reçu.
- **Marge** = réglage `build.margin_rate` (défaut 15 %, groupe `commissions`),
  surchargeable par requête. Pilotable au back-office comme celle du team building :
  c'est un paramètre commercial, pas du code.
- **Traçabilité** : `created_by` = l'agent qui a chiffré.

**Partage des droits** — chiffrer et envoyer relèvent de `gerer:chantiers` ;
**répondre** relève de la policy `respond`, réservée au **client**. Accepter un devis
est son engagement financier : ni l'agent ni l'admin ne le prennent à sa place (même
règle que `TeamBuildingRequestPolicy::accept`, où seule l'entreprise accepte).

**Cycle & statuts du dossier.** L'enum `ConstructionRequestStatus` prévoyait
`EN_ETUDE → DEVIS_ENVOYE → ACCEPTEE` depuis B5 sans que rien ne le pilote ; les devis
l'alimentent enfin : composition → `EN_ETUDE`, envoi → `DEVIS_ENVOYE`, acceptation →
`ACCEPTEE`. Deux garde-fous : un devis **complémentaire** sur un chantier accepté ou
en cours **ne fait pas régresser** son statut, et un **refus** laisse le dossier en
`DEVIS_ENVOYE` (un refus appelle un devis révisé, il n'annule pas la demande).
Un devis déjà envoyé ne se renvoie pas (422) — un second envoi écraserait en silence
la réponse du client. Seul un devis **envoyé** est acceptable ou refusable (422).

### ⚠️ L'acceptation crée une réservation payable (F8.14)

`accept()` ne faisait que **changer deux colonnes `status`**. Le client validait
un chantier à plusieurs millions et **rien ne devenait exigible** : ni montant,
ni écran de règlement, ni relance — `POST /payments/initiate` réclamant un
`booking_id`. C'est le même trou que F8.11 avait comblé sur les devis génériques,
et que F8.14 a trouvé simultanément sur le team building : **trois familles de
devis, trois fois la même coupure** entre l'accord et l'encaissement.

`QuoteConversionService::convertConstruction()` crée la réservation :

- le **devis est lui-même la cible polymorphe** (`bookable_type = ConstructionQuote`) —
  un chantier n'a aucune fiche au catalogue à désigner ; `bookings` est polymorphe
  depuis B3.3, **aucune migration** ;
- **titulaire** = le client du dossier ; ni dates ni participants (un chantier n'en
  a pas), colonnes que `bookings` autorise à nul ;
- ⚠️ **commission = `margin_xof`**, la marge déjà ventilée dans le devis, et non le
  taux commun de `CommissionCalculator` : le total signé la contient déjà ;
- **idempotent** (verrou de ligne + `morphOne`) ;
- `ConstructionQuoteAcceptedNotification` part au client **après** la transaction,
  avec un lien résolu par `SpaceLink` vers `<son espace>/reservations/{id}/paiement`
  (un chantier peut être suivi par un client comme par un compte diaspora).

`ConstructionQuoteResource` expose désormais `booking` (chargé par
`GET /construction-requests/mine`) : sans lui, le montant exigible redeviendrait
invisible au premier rechargement de l'écran.

Tests : `tests/Feature/Build/ConstructionQuoteTest.php` (18 cas).

## Prestataires BTP (F7.3.e3)

Dernière exigence non couverte du module. Même parti pris qu'en F7.2.h : **pas de
table d'affectation dédiée**, chaque affectation crée une **mission Pro** rattachée
au chantier (`provider_missions.construction_request_id`, migration additive). Elle
suit le cycle standard (affectée → acceptée → … → terminée), porte sa **commission
figée** (`CommissionCalculator`) et remonte dans les revenus du prestataire.

⚠️ La colonne `category` du team building est **réutilisée telle quelle** : elle
porte une brique de pack pour une mission TB et un **lot** (`ConstructionLot`) pour
une mission de chantier. C'est la clé étrangère renseignée qui dit quel vocabulaire
lire — une seconde colonne aurait laissé l'une des deux vide sur chaque ligne.

On affecte **par corps d'état**, pas au chantier en bloc : un chantier fait
intervenir un maçon, un électricien et un plombier, chacun sur son lot, chacun avec
son montant et sa commission. Le prestataire doit être **validé** (comme en TB).

| Méthode | URL | Accès |
|---|---|---|
| GET | `/construction-requests/{id}/assignments` | policy `view` — **le client aussi** : il a le droit de savoir qui intervient chez lui |
| POST | `/construction-requests/{id}/assignments` | `gerer:chantiers` |

> ✅ **Écart CDC §7 évité ici** : la garde est une **permission**, pas un rôle. Un
> `agent_kaikun` peut donc affecter un prestataire à un chantier — ce que le cahier
> des charges lui confie. Le team building, lui, exige encore le rôle **admin**
> dans ses policies (`view`/`manage`) et renvoie 403 à l'agent : écart connu,
> toujours à trancher.

Tests : `tests/Feature/Build/ConstructionAssignmentTest.php` (9 cas, dont la
cohabitation de plusieurs corps d'état, le refus d'un prestataire non validé et la
non-régression des missions ordinaires).

> ✅ **Écart d'interface RÉSORBÉ.** Signalé en F7.3.e2 (« `accept` / `refuse` sont
> livrés et testés, mais le client n'a aucun écran pour répondre »), il a été comblé
> en **F3.9** par le bloc `shared/components/construction-quotes/`, monté dans
> `/mon-espace/diaspora`. **F8.14** y a ajouté le maillon suivant : le devis accepté
> y affiche le montant restant dû et le bouton qui mène au règlement — l'écran
> promettait jusque-là que « notre équipe lance le chantier », ce qui était faux
> puisque rien n'était payable.
>
> ⚠️ **Piège de test** : les jalons sont semés par le **contrôleur** `store`, pas
> par la factory — une demande créée directement par le modèle n'en a aucun.
