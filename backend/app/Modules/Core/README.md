# Module Core — Auth, utilisateurs, rôles

Le module **Core** porte tout ce qui est transverse à l'authentification et à la
sécurité des comptes. La phase B0.5 a posé les **3 fondations techniques** ci-dessous ;
la logique métier (inscription, login, définition des 8 rôles...) viendra en **Phase B1**.

---

## 1. Laravel Sanctum — authentification par token

- Package : `laravel/sanctum` (v4).
- Rôle : émettre des **tokens d'API** pour authentifier le frontend Angular et le mobile.
- Activation : trait `HasApiTokens` sur `App\Models\User`.
- Table : `personal_access_tokens`.
- Émettre un token : `$user->createToken('nom')->plainTextToken`.
- Protéger une route : middleware **`auth:sanctum`**.
- Config : `config/sanctum.php`.

## 2. Spatie Laravel-Permission — rôles & permissions

- Package : `spatie/laravel-permission` (v8).
- Rôle : gérer les **8 rôles Kaikun** (Visiteur, Client, Propriétaire, Prestataire,
  Entreprise, Agent Kaikun, Admin, Super Admin) et leurs permissions.
- Activation : trait `HasRoles` sur `App\Models\User`.
- Tables : `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`.
- Vérifier : `$user->hasRole('admin')`, `$user->can('valider-bien')`.
- Protéger une route : middlewares `role:`, `permission:` (Spatie).
- Config : `config/permission.php`.
- ⚠️ Spatie met les rôles/permissions en cache : après modification, lancer
  `php artisan permission:cache-reset`.

> Les 8 rôles ne sont pas encore créés ici — ils le seront via un **seeder** en Phase B1.

## 3. Spatie Activitylog — journal d'audit

- Package : `spatie/laravel-activitylog` (v5).
- Rôle : tracer **qui a fait quoi** (validation de bien, changement de prix,
  validation de paiement, suppression...) — exigence de sécurité (Phase B15).
- Table : `activity_log`.
- Logguer à la main : `activity()->log('message')`.
- Logguer automatiquement un modèle : lui ajouter le trait `LogsActivity`
  (sera fait modèle par modèle dans les phases métier).
- Config : `config/activitylog.php`.

---

## Modèle `User`

Le modèle `App\Models\User` cumule désormais les traits :
`HasApiTokens` (Sanctum), `HasRoles` (Permission), `HasFactory`, `Notifiable`.

---

## Couche de données — Utilisateurs & Profils (phase B1.1)

### Tables

| Table | Rôle | Colonnes clés |
|---|---|---|
| `users` | Identité & connexion | `name`, `email` (unique), `phone` (unique), `email_verified_at`, `phone_verified_at`, `password`, `city`, `status` |
| `profiles` | Casquette métier (1–1) | `user_id` (unique), `type`, `verification_status`, `preferences` (JSON) |

> **Connexion email OU téléphone** : `email` et `phone` sont tous deux uniques.
> **Statut de compte** : `users.status` vaut `en_attente_verification` par défaut
> (le compte devient `actif` après vérification — cf. B1.4 et B15).

### Modèles & emplacements

- `App\Models\User` — reste à l'emplacement Laravel par défaut (compatibilité
  `config/auth.php`, Sanctum, factory). Relation `hasOne(Profile)`.
- `App\Modules\Core\Models\Profile` — dans le module Core. Relation `belongsTo(User)`.
  Sa factory étant hors de `App\Models`, le modèle déclare explicitement
  `newFactory()`.

### Enums associés (`app/Modules/Core/Enums/`)

- `UserStatus` : `en_attente_verification`, `actif`, `suspendu`, `desactive`.
- `ProfileType` : `client`, `proprietaire`, `prestataire`, `entreprise`, `diaspora`.

> ⚠️ **Type de profil ≠ rôle.** Le *type de profil* est la casquette métier
> choisie à l'inscription ; le *rôle* (Spatie, 8 rôles) porte les droits d'accès.
> Le lien entre les deux (rôle attribué selon le type) est fait au seeder en B1.2.

### Rôle (pas de colonne)

Le rôle n'est **pas** une colonne de `users` : il est géré par Spatie
(`model_has_roles`), source de vérité unique. On vérifie via `$user->hasRole(...)`.

---

## Rôles & permissions (phase B1.2)

### Les 8 rôles — `App\Modules\Core\Enums\UserRole`

`visiteur`, `client`, `proprietaire`, `prestataire`, `entreprise`,
`agent_kaikun`, `admin`, `super_admin`.

Créés par le **seeder** `Database\Seeders\RolesAndPermissionsSeeder` (idempotent),
appelé depuis `DatabaseSeeder`. À (re)jouer via `php artisan db:seed`.

### Matrice de permissions initiale

| Rôle | Permissions |
|---|---|
| visiteur, client, proprietaire, prestataire, entreprise | *(aucune perm. d'admin — accès via policies de propriété)* |
| agent_kaikun | `valider:bien/vehicule/experience/prestataire`, `consulter:dashboard-admin`, `moderer:avis` |
| admin | toutes les permissions back-office |
| super_admin | **tout** (+ bypass global) |

> Le jeu de permissions est **minimal pour l'instant** et s'enrichira phase par phase.

### Bypass super_admin

`AppServiceProvider::configureAuthorization()` ajoute un `Gate::before` qui
autorise **toute** capacité si l'utilisateur est `super_admin`. Aucune
restriction ne s'applique donc à ce rôle.

### Mapping type de profil → rôle par défaut

À l'inscription, `UserRole::defaultForProfileType()` attribue :
`client`/`diaspora` → **client**, `proprietaire` → **proprietaire**,
`prestataire` → **prestataire**, `entreprise` → **entreprise**.
(Le profil *diaspora* est un client résidant à l'étranger.)

> ⚠️ Après toute modification de rôles/permissions : `php artisan permission:cache-reset`.
