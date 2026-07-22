# Espace propriétaire (`features/owner/`) — F4

Espace personnel du **propriétaire** de biens, monté sous `/espace-proprietaire`
et **réservé au rôle `proprietaire`** (`roleGuard`). Il permet de déposer et
gérer ses biens, de suivre sa **gestion locative** (loyers, reversements,
incidents) et ses documents.

## Un shell partagé avec l'espace client

Cet espace **ne réinvente pas** l'habillage : il réutilise le **shell générique**
`layouts/space-layout/` (menu latéral sombre pleine hauteur + en-tête épuré),
introduit en F4 par généralisation du shell de l'espace client (F3). Le shell est
**paramétré par espace** via le jeton `SPACE_CONFIG` :

- [`owner-space.ts`](owner-space.ts) exporte `OWNER_SPACE` (`SpaceConfig`) et
  `OWNER_NAV` : la marque « Espace propriétaire », les rubriques du rail, et les
  cibles des liens transverses (cloche de notifications, « Mon profil » — qui
  pointent, pour l'instant, vers les écrans de l'espace client car notifications
  et profil sont propres à l'utilisateur, pas à l'espace).
- [`owner.routes.ts`](owner.routes.ts) monte le `SpaceLayoutComponent` avec cette
  config (`providers: [{ provide: SPACE_CONFIG, useValue: OWNER_SPACE }]`) et
  protège toute la branche par `roleGuard` (`data: { roles: ['proprietaire'] }`).

Comment le propriétaire y arrive : le lien **« Mon espace »** de l'en-tête public
est **routé par rôle** via `core/auth/space-home.ts` (`spaceHomeFor(user)`) — un
propriétaire est envoyé dans `/espace-proprietaire`, un client dans `/mon-espace`.
Il n'atterrit donc plus systématiquement dans l'espace client.

Chaque rubrique du rail porte un drapeau `ready` : les rubriques non encore
construites sont affichées « Bientôt » (aucun lien mort), et passeront à
`ready: true` avec leur sous-phase. **Toutes en place** : Tableau de bord (F4.1),
Mes biens (F4.2), dépôt/édition d'un bien (F4.3), Gestion locative (F4.4) et
Documents (F4.5) — l'espace propriétaire est complet.

## Écrans

- **Tableau de bord** (F4.1) — [`overview/owner-overview-page.ts`](overview/owner-overview-page.ts).
  Interroge `GET /manage/dashboard` (via `core/api/manage.service.ts`) et affiche
  les **agrégats réels** de gestion locative du propriétaire connecté (scopés
  côté backend à ses seuls biens) : mandats actifs, loyers encaissés / impayés,
  dépenses, reversements, incidents ouverts. Les indicateurs qui appellent
  l'attention (impayés, incidents) sont teintés en ambre ; les loyers encaissés
  en vert. Suit une grille de **tuiles** vers les rubriques de l'espace.
- **Mes biens** (F4.2) — [`properties/owner-properties-page.ts`](properties/owner-properties-page.ts)
  (liste) + [`properties/owner-property-detail-page.ts`](properties/owner-property-detail-page.ts)
  (fiche). La liste interroge `GET /properties/mine` (via `PropertyManagementService.mine`)
  et affiche **tous les biens du propriétaire, quel que soit leur statut** — au
  contraire du catalogue public qui ne montre que les biens publiés. Chaque carte
  cliquable porte une **pastille de statut de validation** teintée par tonalité
  (`propertyStatus` → pastille globale `.bk-status[data-tone]`) : publié (vert),
  en attente de validation (or), rejeté (rouge), suspendu/archivé (gris). La
  **fiche** (`GET /properties/mine/{id}`, réservée au propriétaire → 404 sinon)
  détaille le statut avec une explication, la description, les caractéristiques,
  la localisation et les dates, ainsi que le **mode de location** et — le cas
  échéant — le bloc **Nuitées (courte durée)**. Un bouton « Modifier le bien »
  mène au formulaire d'édition (F4.3). Helpers de présentation partagés dans
  [`properties/property-status.ts`](properties/property-status.ts).
- **Déposer / modifier un bien** (F4.3) —
  [`properties/owner-property-form-page.ts`](properties/owner-property-form-page.ts),
  monté sur `biens/nouveau` (création) et `biens/:id/modifier` (édition). **Un
  seul composant sert les deux** : la présence d'un `:id` bascule en édition et
  préremplit le formulaire depuis `GET /properties/mine/{id}`.

  Le cœur de l'écran est le **mode de location**, qui décide de ce qu'on affiche
  et de ce qu'on enregistre :

  | Mode | Champs affichés | Enregistrement |
  |---|---|---|
  | Mensuelle | loyer mensuel (`price_xof`) | POST/PATCH `/properties` |
  | Nuitées | bloc nuitées (prix/nuit, caution, capacité, nuits min/max, arrivée/départ) | + PUT `/properties/{id}/stay` |
  | Mixte | les deux | POST/PATCH + PUT |

  Le groupe de champs « nuitées » est **désactivé** (donc exclu de la validation)
  quand le mode ne l'inclut pas. À l'enregistrement, le bien est sauvegardé
  d'abord, puis la config nuitées est **réconciliée** : upsert (PUT) si le mode
  l'inclut, **retrait (DELETE)** si le bien en avait une et qu'on repasse en
  mensuelle seule, rien sinon. On redirige ensuite vers la fiche du bien.

  **Photos** (F4.3) — un bien sans photo n'est presque jamais consulté, donc
  l'écran y insiste : le bloc « Photos du bien » permet d'en déposer plusieurs
  (JPEG/PNG/WebP, 5 Mo max, contrôlés en amont pour éviter un 422), de désigner
  la **couverture** (celle des cartes du catalogue) et d'en retirer. En
  **création**, le bien n'existe pas encore : les fichiers sont retenus avec un
  aperçu local puis téléversés **après** la création du bien, séquentiellement
  pour conserver l'ordre choisi. Un encart avertit tant qu'aucune photo n'est
  jointe.

  Autres points : **compte vérifié requis** (les endpoints exigent
  `verified.account` → un encart invite à vérifier sinon) ; **localisation en
  cascade** région → département → commune, dont le préremplissage en édition
  utilise `emitEvent: false` pour ne pas déclencher les remises à zéro en chaîne
  (les ids du référentiel sont exposés par `PropertyResource.location`).
- **Gestion locative** (`manage/`, F4.4) — deux écrans en **lecture seule** sur
  le module Manage backend (les mandats sont établis par les agents Kaikun ; le
  propriétaire consulte) :

  - **Liste des mandats** (`gestion-locative`, `owner-manage-page`) —
    `GET /manage/mandates/mine` (paginé 15/page). Chaque mandat est une carte
    cliquable : bien géré, statut du mandat, loyers encaissés, et — en ambre —
    impayés / incidents ouverts s'il y en a.
  - **Fiche d'un mandat** (`gestion-locative/:id`, `owner-mandate-detail-page`) —
    `GET /manage/mandates/{id}` (réservé au propriétaire, 404 sinon). On y trouve
    un **résumé financier** (5 KPI), les conditions du mandat, les **lignes
    récentes** (loyers, reversements, incidents — les 12 plus récents de chaque),
    et surtout un **rapport mensuel** (`GET .../report?month=YYYY-MM`)
    recalculable via un sélecteur de mois (12 derniers mois). Le rapport donne
    loyers encaissés/impayés, dépenses, **commission Kaikun**, **net à reverser**
    (mis en valeur) et reversements du mois.

  Le helper `mandate-status.ts` ne fournit que les **tonalités** des pastilles
  (`.bk-status[data-tone]`) — les libellés viennent déjà du serveur
  (`status_label`). Les lignes détaillées ne sont chargées que sur la fiche : la
  liste `mine` reste légère (agrégats seulement).
- **Documents** (`documents/`, F4.5) — gestion des pièces justificatives
  **par bien**, en deux écrans :
  - **Liste des biens** (`documents`, `owner-documents-page`) — réutilise
    `GET /properties/mine` (paginé) et affiche, sur chaque carte, une pastille
    **« N documents »** (ambre si aucun, pour inciter) tirée de `documents_count`
    (`withCount` côté serveur). Chaque carte mène à la gestion des documents du
    bien. La présentation calque « Mes biens » (classes `.op-*` redéfinies, car
    scopées par composant).
  - **Documents d'un bien** (`documents/:id`, `owner-property-documents-page`) —
    charge le bien (`GET /properties/mine/{id}`, 404 sinon) pour l'intitulé puis
    ses pièces (`GET /properties/{id}/documents`). Le propriétaire **dépose** une
    pièce (sélecteur de type titre foncier/bail/plan/autre + fichier ; le type
    MIME et la taille — PDF/JPG/PNG ≤ 5 Mo — sont contrôlés **en amont** pour
    éviter un 422 ; envoi `multipart` via `POST`), la **télécharge** (lien
    **signé temporaire** fourni par le serveur, jamais le chemin de stockage) et
    la **supprime** (`DELETE`, après confirmation ; retrait local immédiat). Le
    statut de validation d'une pièce (`En attente de vérification`…) est posé par
    un agent Kaikun — lecture seule ici.
