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
  `/pro/inscription` (F2.7). Formulaire d'adhésion à la marketplace
  (`POST /providers`, service `core/api/provider.service.ts`) : nom commercial,
  catégorie, présentation, certifications. Détecte via `GET /providers/mine` si un
  profil existe déjà (→ rappel du statut plutôt que double inscription).

---

## 3. Espace prestataire connecté (F5)

Monté sous `/espace-prestataire` (rôle `prestataire`), il réutilise le **shell
générique** `layouts/space-layout/` paramétré par une `SpaceConfig` — même
mécanique que l'espace propriétaire (F4). Aucun composant de shell dupliqué.

- **`provider-space.ts`** — `PROVIDER_SPACE` (`SpaceConfig`) + `PROVIDER_NAV` :
  les **7 rubriques** de l'espace (Tableau de bord, Mes services, **Mes offres**,
  Disponibilités, Missions reçues, Avis reçus, Revenus & commissions), chacune
  avec un drapeau `ready`. Depuis F5.6, **toutes** les rubriques sont construites
  et cliquables (le drapeau `ready` reste le mécanisme d'ajout progressif « sans
  lien mort »).
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
  compléter l'inscription via `/pro/inscription`).
- **`services/`** — `ProviderServicesPageComponent`, route `services` (F5,
  « Mes services »). Écran d'**édition du dossier** chargé via `GET /providers/mine`,
  en deux volets : le **descriptif du service** (raison sociale, catégorie,
  présentation — formulaire réactif enregistré par `PUT /providers/mine`) et les
  **documents de certification** (liste avec pastille Vérifiée / En vérification,
  suppression avec confirmation via `DELETE /providers/certifications/{id}`, et
  formulaire d'ajout `POST /providers/certifications`). Depuis **F8.0**, chaque
  certification porte son **justificatif** : champ fichier facultatif au
  formulaire d'ajout (PDF/JPG/PNG ≤ 5 Mo), et dans la liste soit un **lien de
  téléchargement** (URL signée 10 min, `download_url`) soit la mention explicite
  « Aucun justificatif joint » — un blanc laisserait croire que la pièce a été
  reçue. Le `File` vit **hors du `FormGroup`** (un `<input type="file">` ne se
  pilote pas par `formControlName` : sa valeur est un chemin factice) et
  `resetCertFileInput()` vide le champ natif après un ajout réussi, que
  `certForm.reset()` ne touche pas. Un **422 est affiché tel quel** (format,
  taille) : un message générique ferait réessayer le même fichier trop lourd en
  boucle. Trois règles rappelées à
  l'écran : enregistrer une modification **ne relance pas la validation**, une
  certification ajoutée reste « En vérification » jusqu'à revue back-office, et
  **sans justificatif joint l'agent n'a rien à contrôler**. Gère
  les mêmes cas que le tableau de bord — chargement, échec réseau, et le **404
  « pas encore de profil »** (→ `/pro/inscription`).
- **`offers/`** — dépôt des **offres réservables** du prestataire (F5.6),
  routes `offres`, `offres/vehicule/nouveau`, `offres/vehicule/:id/modifier` et
  `offres/experience/nouvelle`. Comble l'exigence CDC §5.2 / §15 (« un
  prestataire peut proposer véhicule, circuit, pirogue… ») en branchant des
  interfaces sur des endpoints déjà exposés par le backend.
  - `ProviderOffersPageComponent` (`offres`) — liste **Mes offres** en deux
    blocs (Véhicules & mobilité / Circuits & expériences), chargée en parallèle
    via `GET /vehicles/mine` + `GET /experiences/mine` (`forkJoin`). Chaque offre
    affiche son **statut de validation** (pastille `.of-badge` teintée) ; les
    véhicules ont un lien **Modifier**. Boutons de dépôt en tête de chaque bloc.
  - `ProviderVehicleFormPageComponent` (`offres/vehicule/nouveau` &
    `.../:id/modifier`) — dépôt/édition d'un véhicule (`POST /vehicles`,
    `PATCH /vehicles/{id}`). Le **type** propose les 8 catégories distinctes de
    l'enum `VehicleType` (voiture particulière / touristique / navette AIBD / bus
    / minibus / 4x4 / pirogue / chauffeur). Les **champs de conformité** affichés
    dépendent de la famille du type : assurance + identité chauffeur (motorisé)
    ou gilets + conformité météo/prestataire (pirogue) — cf. CDC §12. En édition,
    le véhicule est rechargé via `OfferService.findMyVehicle` (le détail public
    ne renvoie que les véhicules publiés). Gère les 403 (dossier non validé) et
    422 (validation).
  - `ProviderExperienceFormPageComponent` (`offres/experience/nouvelle`) — dépôt
    d'un circuit (`POST /experiences`) : titre, destination, durée, capacité,
    prix par participant, programme et **inclusions** cochées (restauration,
    guide, transport, hébergement → `{ cle: booléen }`). Création uniquement (le
    backend n'expose pas d'édition d'expérience ; la modification passe par le
    back-office).
  - Service dédié **`core/api/offer.service.ts`** (`OfferService`) : constantes
    `VEHICLE_TYPES` / `EXPERIENCE_INCLUSIONS`, helper `vehicleFamily`, et méthodes
    `myVehicles` / `createVehicle` / `updateVehicle` / `findMyVehicle` /
    `myExperiences` / `createExperience`. Le nettoyage des corps n'envoie que les
    champs de conformité pertinents pour la famille du véhicule.

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

- **`earnings/`** — `ProviderEarningsPageComponent`, route `revenus` (F5.3).
  Synthèse revenus & commissions issue de `GET /provider-missions/earnings`
  (agrégat backend scopé au prestataire, pas un calcul côté client — robuste
  au-delà des 15 missions paginées). Deux blocs d'indicateurs : **réalisé**
  (missions terminées → chiffre d'affaires, commission Kaikun, **net encaissé**
  mis en avant en vert) et **à venir** (missions acceptées ou en cours → net
  attendu en doré, engagé mais pas encore encaissé), plus un renvoi vers les
  missions à traiter (affectées) le cas échéant.

- **`availability/`** — `ProviderAvailabilityPageComponent`, route
  `disponibilites` (F5.4). Deux volets, chargés via `GET /providers/availability` :
  un **planning hebdomadaire** (7 jours ; chaque jour ouvert/fermé + heures,
  enregistré en bloc via `PUT .../weekly`) et des **périodes d'indisponibilité**
  ponctuelles (congés — ajout `POST`, suppression `DELETE` avec confirmation) qui
  priment sur le planning. Les lignes du planning sont éditées dans un signal
  local puis renvoyées toutes ensemble ; un garde-fou client vérifie que la fin
  suit le début sur un jour ouvert.

- **`reviews/`** — `ProviderReviewsPageComponent`, route `avis` (F5.5). Écran
  **« Avis reçus »** issu de `GET /providers/reviews` : réunit les avis publiés
  sur les ressources du prestataire (véhicules, expériences) **et** les avis
  **directs** déposés après une mission terminée. En tête, une **synthèse de
  notation** (note moyenne, total, **histogramme** de répartition 5★→1★ construit
  par un `computed`) ; en dessous, la **liste des avis** (pastille d'initiale,
  auteur, `source` — « Prestation directe » ou nom de la ressource —, étoiles,
  commentaire, date). Un **état vide** encourageant tant qu'aucun avis n'est
  publié. Note et liste proviennent de la même requête backend → cohérence garantie.

> Le modèle `models/provider.model.ts` (types `Provider`, `ProviderMission`,
> `MissionAction`, `ProviderEarnings`, `WeeklyAvailability`, `Unavailability`,
> `ProviderReviews`…) et le service `core/api/provider.service.ts` centralisent
> les appels ; `mine()` préexistait (F2.7), puis `myMissions()` /
> `transitionMission()` (F5.2), `earnings()` (F5.3), `availability()` /
> `saveWeekly()` / `addUnavailability()` / `removeUnavailability()` (F5.4),
> `updateProfile()` / `addCertification()` / `removeCertification()`
> (« Mes services ») et enfin `reviews()` (F5.5) ont été ajoutés au fil des
> sous-phases.
