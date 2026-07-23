# `pro/` — Kaikun Pro (F2.5) + espace prestataire (F5)

> **En une phrase :** la page qui recrute les **prestataires et entreprises**
> (F2.5), le **formulaire d'adhésion** à la marketplace (F2.7) et, une fois
> connecté, l'**espace prestataire** (F5) pour piloter son activité.

---

## 1. Expliqué simplement

Une page (`/pro`) tournée vers les professionnels : les atouts de rejoindre
Kaikun 360 (demandes qualifiées, label de confiance, paiements sécurisés), à qui
elle s'adresse, et les étapes pour devenir prestataire vérifié. Contrairement
aux autres pages de conversion, l'appel à l'action n'est pas une demande de
service mais une **inscription** : les boutons mènent à
`/auth/inscription` (créer un compte pro) et `/auth/connexion`.

---

## 2. Détails techniques

- **`pro-page/`** — `ProPageComponent`, route `/pro`. Page **statique** de
  présentation (aucun appel API, aucun formulaire de demande) ; les CTA sont de
  simples `routerLink` vers l'authentification. La gestion des missions et des
  certifications vit dans l'espace connecté / back-office.
- Styles entièrement partagés : `.uni-hero` + sections `.conv-*` (grille
  d'audiences `.conv-features`, étapes `.conv-steps`, appel à l'action `.conv-cta`
  avec `.conv-cta-actions` pour les boutons).
- **`provider-registration/`** — `ProviderRegistrationPageComponent`, route
  `/devenir-prestataire` (F2.7). Formulaire d'adhésion à la marketplace
  (`POST /providers`, service `core/api/provider.service.ts`) : nom commercial,
  catégorie, présentation, certifications. Détecte via `GET /providers/mine` si un
  profil existe déjà (→ rappel du statut plutôt que double inscription).

---

## 3. Espace prestataire connecté (F5)

Monté sous `/espace-prestataire` (rôle `prestataire`), il réutilise le **shell
générique** `layouts/space-layout/` paramétré par une `SpaceConfig` — même
mécanique que l'espace propriétaire (F4). Aucun composant de shell dupliqué.

- **`provider-space.ts`** — `PROVIDER_SPACE` (`SpaceConfig`) + `PROVIDER_NAV` :
  les **6 rubriques** de l'espace (Tableau de bord, Mes services, Disponibilités,
  Missions reçues, Avis reçus, Revenus & commissions), chacune avec un drapeau
  `ready`. Seules les rubriques construites sont cliquables ; les autres,
  « Bientôt », passeront à `ready: true` avec leur sous-phase (F5.2 → F5.5).
- **`provider.routes.ts`** — `PROVIDER_ROUTES` : `SpaceLayoutComponent` +
  `providers: [{ provide: SPACE_CONFIG, useValue: PROVIDER_SPACE }]`, protégé par
  `roleGuard` (`data: { roles: ['prestataire'] }`). Profil et notifications sont
  des écrans **transverses réutilisés** (composants de l'espace client), montés
  ici pour que l'espace reste **autonome** (aucun renvoi vers un autre espace).
- **`overview/`** — `ProviderOverviewPageComponent`, route `''` (F5.1). Tableau de
  bord : appelle `GET /providers/mine` et affiche l'**état du dossier** — bandeau
  de **statut de validation** (pastille `.bk-status` teintée), 4 indicateurs
  (note moyenne, avis reçus, certifications vérifiées/en cours, avertissements),
  la **liste des certifications** puis les tuiles des sections. Gère trois cas :
  chargement, échec réseau, et le **404 « pas encore de profil »** (→ invitation à
  compléter l'inscription via `/devenir-prestataire`).
- **`missions/`** — `ProviderMissionsPageComponent`, route `missions` (F5.2).
  Liste paginée des missions (`GET /provider-missions/mine`, 15/page) : chaque
  carte affiche montant, commission Kaikun, **net** prestataire (montant −
  commission), date prévue et statut (pastille `.bk-status` teintée). Selon le
  statut, des **boutons d'action** appliquent une transition
  (`PATCH /provider-missions/{id}/{action}`) : `affectee` → Accepter / Refuser,
  `acceptee` → Démarrer, `en_cours` → Marquer terminée ; les missions clôturées
  (terminée / refusée / annulée) n'ont pas d'action. « Refuser » demande une
  confirmation. Le backend valide la transition (422 si impossible, 403 si ce
  n'est pas le prestataire affecté) ; en cas de succès la mission est **remplacée
  par sa version à jour** dans la liste, sinon un message d'erreur s'affiche.

> Le modèle `models/provider.model.ts` (types `Provider`, `ProviderMission`,
> `MissionAction`…) et le service `core/api/provider.service.ts` centralisent les
> appels ; `mine()` préexistait (F2.7), `myMissions()` / `transitionMission()`
> ont été ajoutés en F5.2.
