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
| B13.4 | Paramétrage : settings (✅) + FAQ + Pages (à venir) | 🚧 |
| B13.5 | Export comptable / reporting | à venir |
| B13.6 | Nuitées back-office + consolidation des policies | à venir |

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

Entrée de file normalisée : `{ type, id, reference, label, owner_id, submitted_at }`.

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
