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

Entrée de file normalisée : `{ type, id, reference, label, owner_id, owner, submitted_at }`.
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
`BookingStatus::estAnnulee()`, source de vérité unique.

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
