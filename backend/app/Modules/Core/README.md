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
