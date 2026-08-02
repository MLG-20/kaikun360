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

---

## Endpoints d'authentification (phase B1.3)

| Méthode | URL | Accès | Rôle |
|---|---|---|---|
| POST | `/api/v1/auth/register` | public | Inscription |
| POST | `/api/v1/auth/login` | public | Connexion |
| POST | `/api/v1/auth/two-factor` | public | Second facteur back-office (2FA, F7.1.d) |
| POST | `/api/v1/auth/logout` | `auth:sanctum` | Déconnexion |

### `register`
Corps : `name`, `email`, `phone?`, `city?`, `password` (+ `password_confirmation`),
`profile_type` (`client`|`proprietaire`|`prestataire`|`entreprise`|`diaspora`).
Crée **User + Profile + rôle par défaut** dans une **transaction**, journalise
l'inscription (audit), puis renvoie `{ data: { user, token } }` en **201**.
Le compte démarre au statut `en_attente_verification`.

### `login`
Corps : `login` (e-mail **ou** téléphone) + `password`. Détection automatique du
type d'identifiant. Échec → **422** générique (`Identifiants invalides.`) sans
révéler quel champ est faux (anti-énumération de comptes). Succès → `{ data: { user, token } }`.

**Exception 2FA (F7.1.d)** : si le compte porte un rôle à fort privilège
(`admin` / `super_admin`, cf. `UserRole::twoFactorRequired()`), le login **ne
renvoie PAS de jeton**. Il envoie un code à 6 chiffres par e-mail et répond
`{ data: { two_factor_required: true, channel: "email", login } }`. Le frontend
doit alors résoudre le défi via `two-factor`.

### `two-factor` (2FA back-office)
Corps : `login` (le même identifiant qu'à l'étape login) + `code` (reçu par
e-mail). Vérifie le code (`VerificationService`, purpose `two_factor`) et, en cas
de succès, délivre un jeton à **expiration courte** (session back-office = 8 h) :
`{ data: { user, token, expires_at } }`. Réponses génériques en cas d'échec
(**422**, `code` invalide/expiré) — pas de distinction identifiant/code. Le
périmètre 2FA est piloté par `UserRole::twoFactorRequired()` (extensible aux
agents sans toucher au flux).

### `logout`
Révoque **uniquement** le token de la requête courante (les autres appareils
restent connectés).

### Briques associées
- Form Requests : `RegisterRequest`, `LoginRequest` (validation stricte, messages FR).
- API Resources : `UserResource` (sans données sensibles, rôles + profil), `ProfileResource`.

> 🔒 À durcir en **phase B15** : limitation de débit spécifique sur `login`/`register`
> (anti brute-force), au-delà du throttle global de 60/min.

---

## Vérification & récupération de compte (phase B1.4)

### Mécanisme de codes

Codes à **6 chiffres**, **usage unique**, **valables 15 min**, stockés **hachés**
(table `verification_codes`, modèle `VerificationCode`). Toute la logique est dans
`App\Modules\Core\Services\VerificationService` (génération, envoi, vérification).

Envoi via la **Notification** `VerificationCodeNotification`. Le canal du code
(`email` / `phone`) dit ce qu'il **confirme** ; le média employé, lui, dépend de
la configuration SMS : un code « téléphone » part par **e-mail** tant qu'aucun
fournisseur SMS réel n'est branché (`SMS_PROVIDER=log`), faute de quoi
l'utilisateur ne recevrait rien. Voir
[`app/Support/Notifications/README.md`](../../Support/Notifications/README.md#repli-e-mail-des-codes-de-vérification).

Les réponses portent ce média réel, pour que le frontend annonce le bon endroit
où chercher le code : `verification.phone_delivery` (`PATCH /users/me`) et
`delivery` (`POST /auth/verify/send`), tous deux valant `'sms'` ou `'mail'`.

En dev (`MAIL_MAILER=log`/`array`), le code n'est pas réellement envoyé.

### Endpoints

| Méthode | URL | Accès | Rôle |
|---|---|---|---|
| POST | `/api/v1/auth/verify/send` | `auth:sanctum` | (Re)envoyer un code (`channel`: email/phone) |
| POST | `/api/v1/auth/verify` | `auth:sanctum` | Vérifier (`channel` + `code`) → active le compte |
| POST | `/api/v1/auth/password/forgot` | public | Demander un code de reset (`login`) |
| POST | `/api/v1/auth/password/reset` | public | Réinitialiser (`login` + `code` + `password`) |

- À l'**inscription**, un code de vérification e-mail est envoyé automatiquement.
- La vérification de l'**e-mail** fait passer le compte de `en_attente_verification`
  à `actif`.
- `password/forgot` répond **toujours** de la même manière (anti-énumération de comptes).
- Après un reset, **tous les tokens** de l'utilisateur sont révoqués (sécurité).

---

## Profil & documents de l'utilisateur connecté (phase B1.5)

### Endpoints

| Méthode | URL | Accès | Rôle |
|---|---|---|---|
| GET | `/api/v1/users/me` | `auth:sanctum` | Profil de l'utilisateur connecté |
| PATCH | `/api/v1/users/me` | `auth:sanctum` | MAJ partielle (`name`, `email`, `phone`, `address`, `region_id`/`department_id`/`commune_id`, `city`, `preferences`) |
| PATCH | `/api/v1/users/me/password` | `auth:sanctum` | Changer le mot de passe (`current_password` + `password` confirmé) |
| GET | `/api/v1/users/me/documents` | `auth:sanctum` | Lister ses pièces |
| POST | `/api/v1/users/me/documents` | `auth:sanctum` | Déposer une pièce (`type` + `file`) |
| GET | `/api/v1/users/me/documents/{document}/download` | **URL signée** | Télécharger un fichier |
| POST | `/api/v1/users/me/avatar` | `auth:sanctum` | Déposer / remplacer sa photo (ou son logo) — multipart, champ `avatar` |
| DELETE | `/api/v1/users/me/avatar` | `auth:sanctum` | Retirer sa photo (idempotent) |
| GET | `/api/v1/users/me/notifications` | `auth:sanctum` | Lister ses notifications (paginé) + `unread_count` |
| GET | `/api/v1/users/me/notifications/unread-count` | `auth:sanctum` | Nombre de non-lues (pastille) |
| PATCH | `/api/v1/users/me/notifications/read-all` | `auth:sanctum` | Marquer toutes ses non-lues comme lues |
| PATCH | `/api/v1/users/me/notifications/{notification}/read` | `auth:sanctum` | Marquer une notification comme lue |

> **Coordonnées & sécurité (F3.2b).** `PATCH /users/me` accepte désormais
> l'**e-mail** et le **téléphone** : les changer remet le canal concerné à « non
> vérifié » et **renvoie un code au nouveau contact** (la réponse porte
> `data.verification.{email_required,phone_required}` ; l'utilisateur confirme
> via `POST /auth/verify`). La **localisation** est structurée en cascade
> Région → Département → Commune (référentiel géo des biens, cohérence validée)
> + une **adresse** libre ; la `city` texte est dérivée de la commune/du
> département pour compatibilité. Le **mot de passe** se change via un endpoint
> dédié exigeant le mot de passe actuel (`current_password:sanctum`) et révoquant
> les autres jetons d'accès.

> **Photo de profil / logo d'entreprise (F8.0).** `AvatarController` + colonne
> `profiles.avatar_path`. **Une seule colonne pour deux usages** :
> `Profile::avatarKind()` renvoie `logo` pour un profil *entreprise* et `photo`
> sinon ; `ProfileResource` expose `avatar_url` + `avatar_kind`, et c'est ce
> drapeau — pas le rôle — qui dit à l'interface quoi demander. Dupliquer la
> colonne aurait imposé de choisir laquelle lire à chaque lecture.
>
> ⚠️ **Disque PUBLIC (`Profile::AVATAR_DISK`), à l'inverse du KYC.** Une image
> affichée en permanence (en-tête de l'espace, fiche prestataire) ne peut pas
> dépendre d'une URL signée : elle casserait au bout de 10 minutes, en pleine
> page. La contrepartie est une validation stricte dans `UpdateAvatarRequest` —
> `image` **en plus de** `mimes` (le contenu réel est vérifié, pas l'extension),
> **ni PDF ni SVG** (ce dernier peut embarquer du script et serait servi tel
> quel), 100×100 à 4000×4000 px, 2 Mo. Rien de sensible n'y est déposé : les
> pièces d'identité restent sur le disque privé (`user_documents`).
>
> Trois règles que les tests verrouillent : un **remplacement supprime l'ancien
> fichier** (sinon un orphelin par changement de photo, jamais nettoyé) ; le
> `DELETE` est **idempotent** (un double clic n'est pas une erreur — le compte
> est déjà sans image) ; et `AccountAnonymizer` **efface la photo, fichier
> compris** (un visage servi publiquement après suppression du compte serait une
> fuite). Les deux routes renvoient l'utilisateur **complet** plutôt que la seule
> URL, pour que le frontend n'ait qu'une source de vérité à rafraîchir.
>
> ⚙️ **Déploiement** : nécessite `php artisan storage:link`.
>
> Tests : `tests/Feature/Core/UserAvatarTest.php` (10 cas).

> **Centre de notifications (F3.6).** Le canal `database` de Laravel alimente
> l'écran « Mes notifications » de l'espace client. Chaque flux métier
> (avancement d'une demande, devis reçu, réservation confirmée) écrit une ligne
> dans la table `notifications` via `toArray()` (catégorie, titre, corps,
> `action_url`). Le `NotificationController` n'agit **que** sur les notifications
> de l'utilisateur courant (`$request->user()->notifications()`) : une
> notification inexistante **ou d'autrui** renvoie un 404 (aucune fuite). La
> liste joint `unread_count` aux métadonnées ; le marquage est idempotent.

### Documents (KYC) — stockage sécurisé

- Fichiers stockés sur le disque **privé** `local` (`storage/app/private`),
  rangés par utilisateur, nom de fichier aléatoire.
- Formats acceptés : PDF/JPG/PNG, **5 Mo** max (`StoreDocumentRequest`).
- Table `user_documents` (modèle `UserDocument`), type via enum `DocumentType`,
  statut de validation `pending` par défaut (validé par un agent en B13).
- Accès au fichier **uniquement** par **URL signée temporaire** (10 min),
  générée dans `UserDocumentResource` — le chemin réel n'est jamais exposé.

### Policy `UserPolicy`

`viewProfile` / `updateProfile` / `manageDocuments` : autorisés si l'utilisateur
agit **sur lui-même** ou s'il est **admin** (le `super_admin` passe via `Gate::before`).
Enregistrée dans `AppServiceProvider`. Les endpoints `/me` sont déjà auto-restreints ;
la policy servira surtout aux accès inter-utilisateurs du back-office (B13).
