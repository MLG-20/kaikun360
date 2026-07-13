# `auth/` — Créer un compte et se connecter

> **En une phrase :** tout ce qui permet à une personne d'entrer dans Kaikun 360 —
> créer son compte, se connecter, confirmer son identité et récupérer l'accès si
> elle oublie son mot de passe.

---

## 1. Ce que ça fait, expliqué simplement

Cette partie regroupe **les portes d'entrée** de la plateforme. Concrètement, un
visiteur peut y faire quatre choses (une cinquième arrive bientôt) :

| Ce que la personne veut faire | Où | Ce qui se passe, en clair |
| --- | --- | --- |
| **Se connecter** | `/auth/connexion` | Elle saisit son e-mail (ou téléphone) et son mot de passe. Si c'est correct, elle entre dans son espace. Sinon, un message lui dit que les identifiants sont incorrects. |
| **Créer un compte** | `/auth/inscription` | Elle choisit d'abord **qui elle est** (Client, Propriétaire, Prestataire, Entreprise ou Diaspora), puis remplit ses informations. Son compte est créé immédiatement. |
| **Confirmer son compte** | `/auth/verification` | Juste après l'inscription, on lui envoie un **code** (par e-mail ou SMS). En le saisissant, elle prouve que l'adresse/le numéro est bien le sien : son compte devient **actif**. |
| **Mot de passe oublié** | `/auth/mot-de-passe-oublie` | Elle indique son e-mail ou téléphone, reçoit un **code**, puis choisit un nouveau mot de passe. |
| **Connexion Google** *(à venir, F1.4)* | sur la page de connexion | Un bouton « Continuer avec Google » pour se connecter sans créer de mot de passe. |

### Le parcours typique d'un nouvel utilisateur

1. Il arrive sur **Créer un compte**, choisit son profil (par ex. « Propriétaire »),
   remplit nom, e-mail, mot de passe, et valide.
2. Il est automatiquement emmené sur la page de **vérification** : on lui a envoyé
   un code. Il le recopie → son compte est **activé**.
3. Le voilà connecté, prêt à utiliser la plateforme.

S'il oublie plus tard son mot de passe, il passe par **Mot de passe oublié** :
il reçoit un code, en saisit un nouveau, et se reconnecte.

### Le « décor » des pages

Toutes ces pages s'affichent dans un **écran dédié à la connexion** (à gauche, la
signature de marque Kaikun sur fond bleu nuit avec les arguments de confiance ; à
droite, le formulaire). Volontairement, **le grand menu du site n'apparaît pas
ici** : on veut que la personne se concentre sur une seule action.

### Un choix de sécurité important

Quand une personne se connecte, sa « clé d'accès » (le jeton) est gardée **en
mémoire vive uniquement**, jamais écrite durablement dans le navigateur.
Conséquence concrète : **si elle recharge la page, elle doit se reconnecter.**
C'est volontaire — c'est plus sûr, notamment sur un ordinateur partagé. (Une
reconnexion automatique pourra être ajoutée plus tard si le client le souhaite.)

---

## 2. Détails techniques

Fonctionnalité **chargée à la demande** (`loadChildren` depuis
[`../../app.routes.ts`](../../app.routes.ts)) : le code de l'authentification n'est
téléchargé par le navigateur que lorsqu'on visite une page `/auth/...`, ce qui
allège le premier chargement du site.

### Organisation des fichiers

```
auth/
├── auth.routes.ts          # Déclare les routes /auth/* (voir tableau ci-dessous)
├── auth-layout/            # Le « décor » commun (écran scindé marque + formulaire)
└── pages/
    ├── login/              # Connexion              → /auth/connexion
    ├── register/           # Inscription + profil   → /auth/inscription
    ├── verification/       # Vérification par code   → /auth/verification
    └── forgot-password/    # Mot de passe oublié     → /auth/mot-de-passe-oublie
```

### Routes et branchements backend

| Page (composant) | Route | Accès | Endpoint API appelé |
| --- | --- | --- | --- |
| `LoginPageComponent` | `/auth/connexion` | public | `POST /auth/login` |
| `RegisterPageComponent` | `/auth/inscription` | public | `POST /auth/register` |
| `VerificationPageComponent` | `/auth/verification` | **connecté** (`authGuard`) | `POST /auth/verify/send`, `POST /auth/verify` |
| `ForgotPasswordPageComponent` | `/auth/mot-de-passe-oublie` | public | `POST /auth/password/forgot`, `POST /auth/password/reset` |

`authGuard` ([`../../core/guards/auth.guard.ts`](../../core/guards/auth.guard.ts))
protège la vérification : une personne non connectée y est renvoyée vers la
connexion.

### La logique de session : `AuthService`

Tout passe par [`../../core/auth/auth.service.ts`](../../core/auth/auth.service.ts),
le service unique qui parle au backend et retient qui est connecté (via des
*signals*, la mécanique de réactivité d'Angular) :

- `login` / `register` / `loginWithGoogle` — ouvrent une session (stockent le jeton
  en mémoire + l'utilisateur) ;
- `sendVerificationCode(canal)` / `verify(canal, code)` — envoient puis vérifient le
  code ; `verify` met à jour l'utilisateur en session (compte devenu actif) ;
- `forgotPassword(identifiant)` / `resetPassword(...)` — récupération d'accès ;
- `logout` / `clearSession` — ferment la session.

### Choix d'implémentation utiles à connaître

- **Formulaires réactifs** (`ReactiveFormsModule`) avec validations reproduisant
  celles du backend : e-mail valide, mot de passe ≥ 8 caractères, confirmation
  identique (validateur `passwordsMatch`).
- **Erreurs du serveur** : quand le backend refuse (HTTP 422), le message est
  affiché — en bandeau global (connexion) ou **sous le champ concerné** (ex.
  « cet e-mail est déjà utilisé » à l'inscription).
- **Anti-énumération de comptes** : « Mot de passe oublié » affiche toujours le même
  message, que le compte existe ou non (on ne révèle pas qui est inscrit).
- **Styles partagés** : la mise en page commune des pages auth vit dans
  [`../../../styles/_auth.scss`](../../../styles/_auth.scss) (classes `.auth-*`),
  réutilisable par toutes les pages ; chaque page ne garde que son style propre.
