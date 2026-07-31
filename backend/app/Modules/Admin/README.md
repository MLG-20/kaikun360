# Module Admin — Back-office / pilotage (Phase B13)

Ce module regroupe les endpoints **transversaux de back-office** : pilotage,
supervision et paramétrage de la plateforme. Il ne porte pas de domaine métier
propre (ceux-ci vivent dans leurs modules respectifs) ; il **agrège** et
**orchestre** ce que les autres modules exposent.

Accès réservé aux profils back-office. Le socle d'autorisation est la permission
`consulter:dashboard-admin` (lecture), complétée par des permissions plus fines
pour les actions sensibles (`gerer:utilisateurs`, `gerer:parametres`,
`gerer:paiements`). Le rôle `super_admin` court-circuite tout via `Gate::before`.

## Périmètre par sous-phase

| Sous-phase | Contenu | État |
|---|---|---|
| B13.1 | Tableau de bord (`GET /admin/dashboard`) | ✅ |
| B13.2 | File de validation + validation générique par type | ✅ |
| B13.3 | Gestion des utilisateurs (rôles, statut, désactivation) | ✅ |
| B13.4 | Paramétrage (settings) + contenu éditorial (FAQ, Pages) | ✅ |
| B13.5 | Export comptable / reporting | ✅ |
| B13.6 | Nuitées back-office + consolidation des policies | ✅ |
| B13.7 | Vues back-office par module + gestion documentaire | ✅ |
| F7.1.a | Équipe back-office : annuaire, enrôlement, pilotage rôle/statut | ✅ |
| F7.1.b | Délégation des dossiers par personne (« grant pur ») | ✅ |
| F7.1.c | Pointeuse de l'équipe (entrée/sortie + feuille mensuelle) | ✅ |
| F7.2.l | Paramètres & contenu : villes éditables + pilotage des notifications | ✅ |

## F7.1.a — Équipe back-office (« poste de commandement »)

Gestion des **employés du back-office** (agents opérationnels / administrateurs)
par le super administrateur. C'est la première brique de la salle de contrôle
(F7) : on enrôle un membre, on l'annuaire, on le promeut/suspend. La délégation
fine des dossiers qu'il peut traiter (permissions par personne) arrive en F7.1.b,
le pointage des présences en F7.1.c.

Accès **niveau admin** (`can:gerer:utilisateurs` ; les agents en sont exclus),
avec les mêmes garde-fous de hiérarchie que la gestion des comptes publics.
Rôles concernés = `UserRole::staff()` (agent_kaikun, admin, super_admin).

**`GET /api/v1/admin/team`** — annuaire **limité aux rôles d'équipe** (les comptes
publics n'y figurent jamais). Filtres `role`, `status`, `q` (nom / e-mail /
téléphone). Chaque entrée = `TeamMemberResource` (rôle principal + permissions
effectives + statut).

**`POST /api/v1/admin/team`** — enrôle un membre : corps `{ name, email, phone?,
role }` où `role ∈ UserRole::assignableStaff()` (**agent** ou **admin** ;
`super_admin` n'est jamais attribuable). Crée le compte avec un secret aléatoire
jamais communiqué, statut `en_attente_verification`, puis envoie par e-mail un
**code d'invitation** (réutilise le flux de réinitialisation : l'invité définit
son mot de passe via `/auth/password/reset`). Garde-fou d'escalade : **créer un
`admin` exige d'être `super_admin`** → 403 sinon.

**`PATCH /api/v1/admin/team/{member}`** — corps `{ role?, status? }` (au moins un).
`status ∈ {actif, suspendu, desactive}`. Garde-fous : cible hors équipe → **404** ;
auto-modification → **403** ; toucher un `super_admin` sans l'être → **403** ;
attribuer `admin` sans être `super_admin` → **403**. Toute action est tracée au
journal d'audit (Spatie Activitylog).

## F7.1.b — Délégation des dossiers (« grant pur par personne »)

**Changement de modèle d'autorisation.** Auparavant, le rôle `agent_kaikun`
portait en bloc toutes les permissions de traitement : tous les agents avaient
donc exactement les mêmes droits. Depuis F7.1.b, le **rôle n'ouvre que l'accès**
(`consulter:dashboard-admin`) et chaque dossier qu'un sous-admin a le droit de
traiter lui est **délégué individuellement** par le super administrateur
(permissions **directes** Spatie). C'est ce que réclame un back-office « digne
d'une entreprise » : le responsable décide, personne par personne, du périmètre.

Le catalogue des permissions est centralisé dans l'enum
`App\Modules\Admin\Enums\AdminPermission` (libellé, groupe d'affichage, exigence
super_admin). Les **12 permissions délégables** (tout sauf l'accès de base) se
répartissent en trois groupes : *Validation* (biens, véhicules, circuits,
prestataires), *Exploitation* (gestion locative, chantiers, nuitées, demandes,
avis) et *Gouvernance* (utilisateurs, paiements, paramètres).

**`GET /api/v1/admin/team/{member}/permissions`** — renvoie `{ catalog, granted }` :
le catalogue des 12 permissions délégables (`value`, `label`, `group`,
`requires_super_admin`) et la liste des permissions **directes** déjà accordées à
l'agent (= les cases cochées).

**`PUT /api/v1/admin/team/{member}/permissions`** — corps `{ permissions: [...] }`.
La liste **remplace** l'ensemble des permissions directes de l'agent (cases
cochées = liste envoyée ; `[]` retire tout). Garde-fous : cible hors équipe →
**404** ; cible non-agent (un admin a déjà tout) → **422** ; permission hors des
12 délégables → **422** ; déléguer une permission de **gouvernance** sans être
super_admin → **403**. Action tracée au journal d'audit.

> **Impact tests :** l'allègement du rôle a nécessité de rendre « pleinement
> outillés » les agents des tests de modules qui exécutent des actions
> opérationnelles (`$agent->givePermissionTo(AdminPermission::operational())`),
> `operational()` reproduisant exactement l'ancien jeu de droits du rôle.

## F7.1.c — Pointeuse de l'équipe

Suivi des présences des employés du back-office « comme une entreprise » :
pointage d'entrée / de sortie et feuille de présence mensuelle. Une ligne de la
table `attendances` = une **session** (`started_at` → `ended_at`) ; une session
sans sortie = « en poste actuellement ». L'agrégation (jour par jour, cumul
d'heures) est faite par `Services\AttendanceSheet`.

**Périmètre personnel** (tout membre de l'équipe, garde `consulter:dashboard-admin` ;
les actions visent le compte connecté, donc on ne pointe QUE pour soi) :

- **`POST /api/v1/admin/attendance/clock-in`** — ouvre une session. **422** s'il
  reste une session ouverte (on solde d'abord la sortie).
- **`POST /api/v1/admin/attendance/clock-out`** — solde la session ouverte. **422**
  s'il n'y en a aucune.
- **`GET /api/v1/admin/attendance/me`** — mon état courant (`on_duty`, session en
  cours) + mon détail du mois en cours.

**Supervision** (administrateur, garde `gerer:utilisateurs`) :

- **`GET /api/v1/admin/attendance`** — feuille de présence mensuelle. Paramètres :
  `month` (`Y-m`, défaut = mois courant), `user` (identifiant d'un employé →
  **détail** jour par jour ; absent → **récapitulatif** de toute l'équipe :
  total d'heures, jours présents, « en poste » par personne), `format`
  (`json` par défaut, ou `csv` pour l'export téléchargeable). Cibler un compte
  hors équipe → **404**.

Chaque pointage est tracé au journal d'audit.

## B13.1 — Tableau de bord

**`GET /api/v1/admin/dashboard`** (`can:consulter:dashboard-admin`) renvoie une
photographie agrégée, calculée par `Services\DashboardAggregator::snapshot()` :

```jsonc
{
  "data": {
    "queues": {                      // files de validation en attente
      "properties_pending":   0,     // biens en_attente_validation
      "vehicles_pending":     0,     // véhicules en_attente_validation
      "experiences_pending":  0,     // circuits en_attente_validation
      "providers_pending":    0      // prestataires en_attente
    },
    "today": {                       // activité du jour (date serveur)
      "requests":  0,                // demandes reçues aujourd'hui
      "bookings":  0                 // réservations créées aujourd'hui
    },
    "revenue": {                     // estimation (encaissement réel = PayTech B14)
      "gross_volume_xof": 0,         // volume des réservations non annulées
      "commission_xof":   0          // part plateforme des réservations non annulées
    },
    "alerts": {
      "reviews_to_moderate": 0,      // avis en_attente
      "open_incidents":      0       // incidents ouverts
    },
    "kpi": {
      "users_total":           0,
      "providers_validated":   0,
      "properties_published":  0,
      "bookings_total":        0
    }
  }
}
```

Chaque indicateur est une agrégation `COUNT`/`SUM` (aucune collection chargée) :
le dashboard reste léger à volume élevé. Les revenus **excluent** les
réservations annulées (statuts `BookingStatus::estAnnulee()`).

## B13.2 — File de validation & décision générique

Un **unique point d'entrée** pilote la validation de tous les types de
ressources soumis à approbation, sans dupliquer la logique métier. Chaque type
fournit un `Validation\ResourceValidator` (enregistré dans `ValidatorRegistry`)
qui **réutilise** les événements et services de son module :

| Type (`{type}`) | Ressource | Permission fine | Effets de bord réutilisés |
|---|---|---|---|
| `property`   | Bien (Immo)          | `valider:bien`        | événement `PropertyValidated` |
| `vehicle`    | Véhicule (Mobility)  | `valider:vehicule`    | `VehicleComplianceChecker` (blocage 422) + `VehicleValidated` |
| `experience` | Circuit (Explore)    | `valider:experience`  | publication + traçabilité |
| `provider`   | Prestataire (Pro)    | `valider:prestataire` | `ProviderValidationService` (synchro profil) |

**`GET /api/v1/admin/queue`** (`can:consulter:dashboard-admin`) :
- sans paramètre → vue d'ensemble `{ queue: { <type>: { count, items[] } }, total_pending }`
  (aperçu de 15 éléments par type) ;
- `?type=vehicle&per_page=20` → liste paginée normalisée d'un seul type.

Entrée de file normalisée : `{ type, id, reference, label, owner_id, owner, submitted_at, media }`.
Le champ **`owner`** (F7.2.a) porte l'identité + le contact du **déposant**
(`{ id, name, email, phone }`, ou `null` si le compte a disparu) — biens : le
propriétaire ; véhicules/expériences : le prestataire ; prestataires : le
titulaire du compte. Produit par le helper `Validation\OwnerEntry`, avec
**eager-loading** de la relation dans `pendingQuery()` (anti N+1). L'écran de
validation du back-office (F7.2.a) affiche ce déposant pour trancher.

**`PATCH /api/v1/admin/validate/{type}/{id}`** — corps
`{ "decision": "approve"|"reject", "reason"?: string }`.
Autorisation en deux temps : accès back-office (`consulter:dashboard-admin` sur
la route) **puis** permission fine selon le `{type}` (vérifiée dans le
contrôleur). Garde-fous : type inconnu → **404** ; élément déjà validé/refusé →
**422** (`decision`) ; conformité véhicule incomplète → **422** (`compliance`).

## F8.1 — Revue des médias avant publication

**Le manque comblé :** jusqu'ici la file ne transportait que le libellé et le
déposant. Un agent validait donc une annonce **sans jamais avoir vu ses
photos**, et la publiait ainsi sur le site vitrine. Trois ajouts :

**1. La galerie dans la file.** Chaque entrée porte désormais `media`, produit
par le helper `Validation\MediaEntry` (pendant d'`OwnerEntry`) :

```
media: { total, images, videos, hidden, items[] }
items[]: { id, reference, type, url, original_name, mime_type, size_bytes,
           is_primary, position, status, status_label, is_hidden }
```

`items` est **borné à 4 vignettes** dans la file (`MediaEntry::PREVIEW`) —
`total` reste le compte réel. Eager-loading de `allMedia` dans `pendingQuery()`
(anti N+1). Les prestataires n'étant pas illustrables (absents de
`Media::TYPES`), leur `media` est vide mais **présent** : la file garde la même
forme d'un onglet à l'autre.

**2. `GET /api/v1/admin/queue/{type}/{id}`** — dossier complet, pour les cas
douteux : `{ entry, is_pending }` où `entry` ajoute la galerie **entière** et
`fields`, un dictionnaire libellé → valeur propre au type (prix, localisation,
description pour un bien ; assurance, chauffeur, gilets pour un véhicule…),
produit par `toDetail()` sur chaque validateur. Consultable **même après
décision** (`is_pending` à faux) : un agent doit pouvoir rouvrir un dossier
qu'il vient de trancher. Garde `consulter:dashboard-admin` seulement —
consulter n'est pas modérer.

**3. `PATCH /api/v1/admin/media/{media}/status`** — corps `{ "status":
"actif"|"masque" }`. Écarter **une** photo floue ou hors sujet plutôt que de
refuser toute l'annonce et renvoyer le déposant à zéro. Le média n'est pas
supprimé : il sort de `media()` (public) mais reste dans `allMedia()`
(back-office), sans quoi l'agent ne pourrait jamais le rétablir. Autorisation :
la **permission fine du type parent** (`valider:bien` pour la photo d'un
bien) — qui peut publier une ressource peut arbitrer ce qu'elle montre. Média
orphelin ou rattaché à un type non validable → **403**.

Les listes de supervision (`/admin/properties`, `/admin/vehicles`,
`/admin/experiences`) exposent en plus `media_count` et `media_hidden_count`
(`withCount`), pour repérer depuis la liste une annonce **publiée sans aucun
visuel** — anomalie visible par les clients.

> **Dette B12 rattrapée ici.** Seul `Property` portait une relation `media()`.
> `Vehicle` (« galerie média sera branchée en B12 ») et `TourismExperience`
> acceptaient déjà des dépôts via `POST media/upload` sans qu'aucune relation
> ne les relise. Les trois modèles utilisent désormais le trait
> `App\Models\Concerns\HasMedia` (`media()` public / `allMedia()` modération).

## B13.3 — Gestion des comptes utilisateurs

Niveau **admin** : permission `gerer:utilisateurs` (admin + super_admin ; les
agents en sont exclus).

**`GET /api/v1/admin/users`** — liste paginée (profil chargé), filtres `role`,
`status`, `q` (nom / email / téléphone). Utilise le scope Spatie `role()`.

**`GET /api/v1/admin/users/{user}`** — fiche complète pour la page de détail du
back-office (F7.2.f). Renvoie `{ user, documents, activity }` : le compte avec
profil + localisation lisible (`UserResource`), ses **pièces justificatives**
(KYC, `UserDocument`) et son **historique** — les 30 dernières entrées du journal
d'audit dont le compte est le sujet, avec l'acteur (`causer`). Répond à
l'exigence CDC §6 « historique » du module *Utilisateurs*.

**`PATCH /api/v1/admin/users/{user}`** — corps `{ role?, status? }` (au moins un,
`required_without`). `role` remplace le rôle principal (`syncRoles`), `status`
pilote actif / suspendu / désactivé / en attente. Deux garde-fous de hiérarchie :

- **escalade de privilèges** : attribuer `admin`/`super_admin` exige d'être
  super_admin ; un compte super_admin n'est modifiable que par un super_admin ;
- **auto-modification** : on ne modifie pas son propre compte ici (évite
  l'auto-verrouillage / l'auto-rétrogradation) → **403**.

Toute mise à jour est tracée dans le journal d'activité (Spatie Activitylog).

## B13.4 — Paramétrage global & référence

Permission `gerer:parametres` (settings) / `consulter:dashboard-admin` (référence).

**Réglages** — stockage clé-valeur typé (`App\Models\Setting`) piloté par
`App\Support\SettingsRepository` et exposé via la façade statique
`App\Support\Settings`. Les clés connues ont une valeur par défaut en code
(`SettingsRepository::DEFAULTS`) ; une ligne en base = une surcharge. Lectures
mises en cache, invalidées à l'écriture.

Réglages livrés : `commission.default_rate` (12), `teambuilding.margin_rate`
(15), `platform.currency`, `support.email`, `support.phone`. Les deux premiers
sont **effectivement lus** par `CommissionCalculator` et `TeamBuildingQuoteComposer`
(constante conservée en repli).

- **`GET /admin/settings`** → liste `[{ key, value, type, group, overridden }]`
  (défauts fusionnés avec les surcharges).
- **`PATCH /admin/settings`** — corps `{ "settings": { "<clé>": <valeur> } }`.
  Seules les clés connues sont acceptées (sinon **422**) ; les taux doivent être
  numériques (sinon **422**).

**Référence (lecture seule)** — **`GET /admin/reference`** renvoie les
nomenclatures définies en dur (`categories` : provider / property_type /
service_type / vehicle_type, issues des enums) et le référentiel géographique
(`geography.regions`), pour alimenter les listes déroulantes du back-office.

**Contenu éditorial (FAQ & Pages)** — modèles transversaux `App\Models\Faq` et
`App\Models\Page`. Lecture **publique**, édition réservée à `gerer:parametres` :

- FAQ : `GET /faqs` (public, publiées triées par `position`) ;
  `GET|POST /admin/faqs`, `PATCH|DELETE /admin/faqs/{faq}` (back-office, voit tout).
- Pages : `GET /pages/{slug}` (public, publiées seules → 404 sinon, résolues par
  slug) ; `GET|POST /admin/pages`, `PATCH|DELETE /admin/pages/{page}`. Slug unique
  (`regex [a-z0-9-]`), duplication → **422**.

**Messages de contact (F2.8.1)** — modèle transversal `App\Models\ContactMessage`
(`App\Http\Controllers\ContactController`). Dépôt **public** `POST /contact`
(throttle 10/min, émetteur non authentifié → `name`/`email` stockés, statut
`nouveau`, événement n8n `contact.received`). Traitement par l'équipe (permission
`traiter:demandes`) : `GET /admin/contact-messages` (filtrable `?status=`),
`PATCH /admin/contact-messages/{contactMessage}` (bascule `nouveau`/`traite`,
enregistre `handled_by`/`handled_at`).

**Coordonnées du siège (F2.8.1)** — réglages `contact.address`,
`contact.latitude`, `contact.longitude` (groupe `general`, cf.
`SettingsRepository::DEFAULTS`), **modifiables via `PATCH /admin/settings`**
(`gerer:parametres`). Exposés en lecture publique par `GET /contact-info`
(+ `support.email`/`support.phone`) pour alimenter l'adresse et la carte de la
page Contact — rien n'est codé en dur côté frontend.

## B13.5 — Export comptable & reporting

Permission `gerer:paiements`. `Services\AccountingReporter::report(from, to)`
consolide sur une période : réservations (volume + commission, **hors
annulées**) et reversements propriétaires **effectués** (module Manage).

**`GET /admin/reports/export`** — paramètres `from`, `to` (dates, `to` ≥ `from`),
`format` (`json` défaut | `csv`).

- JSON : `{ period, summary { bookings_count, active_bookings_count,
  gross_volume_xof, commission_xof, payouts_count, payouts_total_xof },
  bookings[], payouts[] }`.
- CSV : téléchargement du grand livre des réservations (`streamDownload`,
  `fputcsv`), colonnes `reference,date,type,amount_xof,commission_xof,status`.

L'encaissement réel relèvera de PayTech (B14) ; ici les montants sont ceux figés
à la réservation.

**Consommateur (depuis F7.3.d)** : l'onglet *Export comptable* de l'écran
Paiements du back-office. Il appelle le format JSON pour afficher la période
(totaux + grand livre + reversements), puis le CSV sur les **mêmes** bornes.
⚠️ Le CSV ne contient que les réservations : si les reversements doivent y
figurer un jour, c'est ici qu'il faut agir (un paramètre `scope`), pas côté
frontend.

## B13.6 — Exploitation des nuitées & matrice de rôles

**Nuitées** (permission `gerer:nuitees`, agents + admin). Données d'exploitation
portées par `bookings` (`checked_in_at`, `checked_out_at`, `housekeeping_status`
= enum `App\Enums\HousekeepingStatus` a_faire/en_cours/fait). Opérations
réservées aux réservations de type Stay (sinon **422**).

- `GET /admin/stays/calendar` — calendrier global paginé (filtres `from`/`to`
  sur la date d'arrivée ; titre du bien pré-chargé).
- `PATCH /admin/stay-bookings/{booking}/check-in` — arrivée (double → 422).
- `PATCH /admin/stay-bookings/{booking}/check-out` — départ (exige une arrivée,
  double → 422) ; bascule le ménage sur `a_faire`.
- `PATCH /admin/stay-bookings/{booking}/housekeeping` — statut de ménage.
- `PATCH /admin/stay-bookings/{booking}/caution` — **sort de la caution** (F7.3.f) :
  `restituee` ou `perdue`.

### Caution des nuitées (F7.3.f)

La caution était **recopiée** sur la réservation (`bookings.caution_xof`) mais son
statut restait `null` pour un séjour : ni retenue, ni restitution, alors que la
location de véhicule pilote ce cycle depuis B7.4 et que l'enum `CautionStatus` était
déjà pensée transversale. Le module *Nuitées* du CDC §6 était donc incomplet.

- **Retenue à la réservation** : `StayBookingController@store` renseigne désormais
  `caution_status = retenue` dès qu'un logement demande une caution (`null` sinon —
  il n'y a rien à rendre).
- **Tranchée au départ**, depuis le back-office. Trois garde-fous côté serveur :
  **départ enregistré exigé** (restituer avant le départ n'a pas de sens ; conserver
  pendant le séjour, c'est trancher avant l'état des lieux — le ménage, lui, n'est
  pas attendu), **caution encore retenue** (on ne rejoue pas une décision), et
  **motif obligatoire pour la conserver** (un litige est possible ; la restitution
  n'a rien à justifier).
- **Tracée** au journal d'audit avec le motif et le montant (« Caution restituée » /
  « Caution conservée »).
- `caution_xof` et `caution_status` sont exposés dans le calendrier et dans le
  résumé d'opération — sans eux l'écran ne peut ni afficher la caution ni savoir
  s'il reste une décision à prendre.

Tests : `tests/Feature/Admin/StayCautionTest.php` (10 cas).

> ⚠️ **Pas de restitution partielle** : l'enum n'a que trois états (retenue,
> restituée, perdue), comme pour les véhicules. Retenir une partie de la caution
> demanderait un montant retenu en base — décision produit non prise.

**Matrice de rôles (policies différenciées)** — verrouillée par test
(`BackOfficeRoleMatrixTest`) :

| Rôle | Périmètre |
|---|---|
| **agent_kaikun** | opérationnel : validation, modération, gestion locative/chantiers/nuitées, traitement des demandes, dashboard. **Pas** de comptes / paiements / paramètres. |
| **admin** | l'ensemble du back-office. |
| **super_admin** | court-circuite toute autorisation (`Gate::before`) ; seul à pouvoir attribuer les rôles d'administration. |

## B13.7 — Vues back-office par module & gestion documentaire

Couche de supervision unifiée sous `/admin`, réutilisant les Resources des
modules pour un format identique.

**Catalogues (tous statuts)** — `consulter:dashboard-admin`, contrairement aux
catalogues publics limités aux publiés :
`GET /admin/properties`, `GET /admin/vehicles`, `GET /admin/experiences`
(filtres `status`, `type`, `owner_id`/`provider_id`, `q`).

### Correction & archivage d'un bien (F7.3.g)

Solde la **dette CDC §15** (« un admin peut modifier ») et la ligne §6 *Biens
immobiliers* : valider et publier existaient (B2.4), **modifier** et **archiver**
n'avaient aucune route back-office. Garde **`valider:bien`** — celui à qui l'on
confie déjà la publication ou le rejet d'une annonce peut en corriger le titre
(ce qui ouvre le geste à l'`agent_kaikun`, conformément au CDC §7, plutôt que de
reproduire l'écart de rôle du team building).

| Méthode | URL | Effet |
|---|---|---|
| PATCH | `/admin/properties/{property}` | corrige les champs (règles du propriétaire réutilisées via `AdminUpdatePropertyRequest`) |
| PATCH | `/admin/properties/{property}/archive` | sort l'annonce du catalogue (motif facultatif, tracé) |
| PATCH | `/admin/properties/{property}/restore` | la renvoie **en file de validation** |

- **Périmètre arbitré** : corriger et archiver, tout étant tracé. **Ni création**
  à la place d'un propriétaire, **ni réattribution** à un autre compte —
  réattribuer change qui touche les loyers, cela ne se rattrape pas d'un clic.
  Le bien reste à son propriétaire (test dédié).
- Le **statut n'est pas modifiable par la correction** : il relève de la file de
  validation et de l'archivage, qui tracent chacun leur décision.
- **Sortir d'archive ne republie pas** : le bien repasse `en_attente_validation`.
  Un bien archivé pour contenu problématique ne doit pas revenir en ligne d'un clic.
- Trace d'audit « Correction de bien (back-office) » avec l'**avant/après** du
  titre et du prix — plus fine que la modification par le propriétaire, parce
  qu'elle porte sur le bien d'un tiers.
- ⚠️ Le propriétaire **n'est pas notifié** (même comportement que le rejet, B2.4).
  Une notification serait un ajout à part entière.

Tests : `tests/Feature/Admin/AdminPropertyEditTest.php` (10 cas).

> ℹ️ Un **admin** pouvait déjà corriger un bien via `PATCH /properties/{property}`
> (policy `update` = propriétaire **ou** rôle admin, B2.3) : ce qui manquait, c'était
> l'archivage, l'ouverture du geste par permission, et l'interface.

**Mobilité (F7.2.j)** — `consulter:dashboard-admin`. Le cahier des charges (§6)
demande de piloter « véhicules, chauffeurs, pirogues, bus, disponibilités,
assurances, capacités » : deux réalités distinctes, donc deux endpoints.

- `GET /admin/vehicles` — la **flotte**. Sert désormais `AdminVehicleResource`,
  **sur-ensemble** du format public : s'y ajoutent les champs de contrôle que le
  catalogue public masque (`insurance_ref`, `driver_identity`,
  `life_jackets_count`, `weather_compliant`, `provider_compliant`) et le
  `provider` (nom + contact, pour joindre en cas d'anomalie). Filtre
  supplémentaire **`driver=1|0`** (avec / sans chauffeur) ; la recherche `q`
  porte aussi sur la `reference`. L'écran Catalogues (F7.2.b) consomme la même
  route et ignore simplement les champs qu'il n'affiche pas.
- `GET /admin/mobility-services` — les **départs programmés**, tous statuts
  (la recherche publique, elle, ne rend que les publiés). Chaque ligne porte son
  **remplissage** : `seats_taken` (somme des participants des réservations non
  annulées, agrégée en **une** requête via `withSum` → pas de N+1) et
  `seats_left` — les « disponibilités » du cahier. Filtres `status`, `type`,
  `provider_id`, période `from`/`to` sur `departure_at`, et `q` (départ,
  destination, référence).

Les **statuts d'annulation** ne sont pas recopiés : ils sont dérivés de
`BookingStatus::estAnnulee()`, source de vérité unique
(`AdminCatalogController::cancelledBookingStatuses()`).

**Tourisme (F7.2.k)** — `consulter:dashboard-admin`. Le cahier des charges (§6)
demande « circuits, destinations, programmes, guides, restaurants, capacités
groupes ». Ces éléments ne vivent pas au même endroit dans le modèle :

- `GET /admin/experiences` — les **circuits**. Sert désormais
  `AdminExperienceResource` (sur-ensemble du format public) avec le
  **remplissage** (`seats_taken`/`seats_left`, `withSum`) et le `provider`.
  ⚠️ Un circuit n'a **pas de date de départ** : sa capacité est un **total par
  circuit** (B6.3), le décompte cumule donc toutes ses réservations non
  annulées — contrairement aux trajets de Mobilité, datés. Filtre
  supplémentaire `destination` (exact) ; `q` porte aussi sur la `reference`.
  Le **« programme »** est rendu par les `inclusions` (restauration, guide,
  transport…), déjà portées par le modèle.
- `GET /admin/tourism/destinations` — les **destinations**. Elles ne sont pas
  une entité en base mais une **colonne** de `tourism_experiences` : on les
  restitue par agrégation (`circuits_count`, `published_count`,
  `pending_count`, `capacity_total`, `seats_taken`/`seats_left`,
  `price_min`/`price_max`). Non paginé (une dizaine de destinations distinctes).
  Le remplissage est calculé par une **requête séparée** puis recollé en
  mémoire : agrégé dans la même requête, la jointure sur `bookings`
  multiplierait les lignes et fausserait `COUNT(*)` / `SUM(capacity)`.
- `GET /admin/providers?category=guide,restauration` — les **guides** et
  **restaurants**. ⚠️ Ce ne sont **pas** des entités du module Explore : la
  plateforme ne les connaît que comme **catégories de prestataires**
  (`ProviderCategory`) et comme drapeaux d'inclusion d'un circuit. Le filtre
  `category` accepte plusieurs valeurs séparées par une virgule ; une valeur
  inconnue est ignorée, mais un filtre dont **aucune** valeur n'est valide
  renvoie une liste vide (jamais le catalogue entier).

> **Écart CDC assumé** : aucun rattachement d'un guide *nommé* à un circuit
> précis n'existe en base. Le combler demanderait un modèle d'affectation
> (guide ↔ circuit) — non livré en F7.2.k, signalé à l'écran.

**Dossiers de suivi** — `consulter:dashboard-admin` :
`GET /admin/construction-requests` (toutes, +counts) et `GET /admin/mandates`
(tous, property/owner + counts rents/incidents/expenses/payouts). Team building
(`GET /team-building-requests`) et diaspora (`GET /diaspora-projects`) exposent
déjà leur file back-office dans leurs modules.

**Gestion documentaire transverse** — sensible (KYC, contrats) → niveau
administrateur `gerer:utilisateurs`. `GET /admin/documents` : vue d'ensemble
(compteurs `kyc` / `property` / `certification` / `payout_proof`) ou liste
normalisée paginée `?type=` (`{ doc_type, id, subject_type, subject_id, label,
original_name, status, created_at }`) ; type inconnu → **404**.

**Avis & qualité (F7.2.g)** — deux vues de supervision (les actions restent
servies par leurs contrôleurs d'origine) :

- `GET /admin/reviews` (`moderer:avis`) — **file de modération** de tous les avis,
  filtrable par `status` (défaut `en_attente`) et `q` (commentaire), normalisée
  (`{ id, reference, rating, comment, status, author, resource_type,
  resource_label, created_at }`). Complète le `GET /reviews` public qui, lui, ne
  rend que les avis publiés d'une ressource précise. La décision reste
  `PATCH /reviews/{review}/moderate` (transversal B12.3).
- `GET /admin/providers` (`valider:prestataire`) — **supervision des
  prestataires** (`ProviderResource` : `rating_avg`/`rating_count`,
  `warnings_count`, `sanction_note`, `status`), filtres `status` + `q`. Les
  sanctions restent `PATCH /providers/{id}/warn|suspend` (module Pro).

## F7.2.l — Paramètres & contenu (CDC §6, dernier des 14 modules)

Complète B13.4 sur ses deux angles morts : les **villes** (référentiel figé) et
les **notifications** (canaux codés en dur). Permission `gerer:parametres`.

### Référentiel géographique éditable (`AdminGeoController`)

Le référentiel — 14 régions, 46 départements, ~557 communes — était semé par
`SenegalGeographySeeder` / `CommunesSeeder` et exposé en **lecture seule** par
`App\Http\Controllers\GeoController` (sélecteurs en cascade du dépôt de bien).
Le cahier des charges demande que l'équipe le maintienne elle-même.

- `GET /admin/geography` — arborescence régions → départements en **un appel**,
  avec `departments_count` et `communes_count` (le total région est la somme des
  compteurs de ses départements : pas de requête supplémentaire). Les communes
  ne sont pas incluses (~557 lignes) : elles se chargent à la demande.
- `GET /admin/communes` — filtres `department_id`, `region_id` (via le
  département parent : une commune ne porte pas de `region_id`), `q`. Chaque
  ligne porte son **usage réel** (`properties_count`, `users_count`) et un
  `deletable` calculé côté serveur — le front n'a pas à rejouer la règle.
- `POST /admin/communes`, `PATCH|DELETE /admin/communes/{commune}`, et les mêmes
  verbes sur `/admin/departments`.

Deux principes de sûreté :

1. **Les régions ne sont pas modifiables.** Nomenclature nationale stable ; les
   ouvrir à l'écriture serait un risque disproportionné face au besoin.
2. **Aucune suppression en cascade silencieuse.** `properties.commune_id` et
   `users.commune_id` sont en `nullOnDelete` → supprimer une commune utilisée
   effacerait la localisation des biens **sans erreur** ; `communes.department_id`
   est en `cascadeOnDelete` → supprimer un département emporterait toutes ses
   communes d'un coup. Les deux cas renvoient **409** avec le décompte de ce qui
   retient l'élément. `AdminGeographyTest` couvre précisément ces refus.

L'unicité est **locale** (nom unique dans le département / la région, comme en
base) : le doublon renvoie **422** avec un message lisible, et un `PATCH` qui ne
change que le `type` ne bute pas sur son propre nom.

### Pilotage des notifications

Réglages `notifications.email_enabled`, `notifications.sms_enabled` (booléens) et
`notifications.events` (map `événement → bool`, groupe `notifications`), avec
`GET /admin/settings` qui renvoie en plus `notification_events` — le catalogue
`App\Support\Notifications\NotificationEvent` (valeur, libellé, description,
public visé, état actif). Ce catalogue vit dans le **code**, pas en base : le
back-office n'a pas à dupliquer ces libellés.

Le point de décision est unique : `App\Support\Notifications\NotificationSettings::channels()`,
appelé par le `via()` des **12 notifications d'exploitation**. Trois règles :
événement coupé → aucun canal (un `via()` vide court-circuite l'envoi, pas même
la trace en base) ; canal coupé → canal retiré ; SMS sans numéro → retiré (règle
qui vivait dupliquée dans plusieurs `via()`). Le canal `database` n'est jamais
coupé par les canaux : gratuit, il alimente « Mes notifications » et fait trace.

Un événement **absent** de la configuration est **actif** : ajouter une
notification au code ne l'éteint jamais par surprise. Corollaire : une clé
inconnue serait ignorée en silence et laisserait croire à une coupure effective —
`PATCH /admin/settings` la **refuse** donc explicitement (422), tout comme une
valeur non booléenne ou un réglage `json` reçu sous forme de chaîne.

> ⚠️ **Les notifications de sécurité sont hors de ce pilotage.**
> `VerificationCodeNotification` (codes de vérification, 2FA admin) ne passe pas
> par le helper : un réglage capable de condamner l'accès au back-office et
> l'inscription n'a pas sa place dans une interface d'administration.

### Catégories — écart CDC assumé

`GET /admin/reference` reste en **lecture seule**. Les catégories sont des enums
PHP (`ProviderCategory`, `PropertyType`, `ServiceType`, `VehicleType`) qui
portent la logique métier : règles de validation, calcul de commission, filtres
de recherche. Les rendre éditables demanderait de sortir cette logique du code —
chantier transversal hors du périmètre de cet écran, signalé à l'utilisateur
dans l'interface plutôt que masqué.
