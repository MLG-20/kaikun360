# Espace entreprise (F6)

Espace connecté des **entreprises** (rôle `entreprise` : entreprises, ONG,
écoles, institutions), monté sous **`/espace-entreprise`**. Il répond au besoin
du cahier des charges (§1.1, §5, §7, §9.4) : **organiser des demandes groupées de
team building** et en suivre les devis.

Comme les espaces client (F3), propriétaire (F4) et prestataire (F5), il réutilise
le **shell générique** `layouts/space-layout/` paramétré par un jeton
`SPACE_CONFIG` — ici [`enterprise-space.ts`](enterprise-space.ts). Toute la
branche est protégée par `roleGuard` (rôle `entreprise`) dans
[`enterprise.routes.ts`](enterprise.routes.ts).

## Écrans

| Rubrique | Route | Backend consommé |
|---|---|---|
| **Tableau de bord** (accueil) | `/espace-entreprise` | — (dérivé de l'utilisateur en mémoire) |
| **Nouvelle demande** | `/espace-entreprise/demandes/nouvelle` | `POST /team-building-requests` |
| **Mes demandes** (historique) | `/espace-entreprise/demandes` | `GET /team-building-requests/mine` |
| **Détail + devis** | `/espace-entreprise/demandes/:id` | `GET /team-building-requests/{id}`, `PATCH /team-building-quotes/{id}/accept` |
| **Messages** | `/espace-entreprise/messages[/:id]` | `GET/POST /messages…` (écrans génériques) |
| **Notifications / Profil** | `/espace-entreprise/{notifications,profil}` | écrans transverses réutilisés |

### `overview/` — Tableau de bord
Page d'atterrissage : salutation, **appel à l'action principal** (« Demander un
pack groupe », cahier §9.4) et tuiles vers les rubriques (`ENTERPRISE_NAV`). Aucun
appel réseau.

### `requests/` — Demandes de team building
- **`enterprise-request-form-page`** : formulaire réactif reprenant exactement les
  informations attendues au cahier §9.4 (participants, ville, dates, budget,
  besoins hébergement/restauration/activités/transport/animation, descriptif).
  Validation miroir du backend (`participants ≥ 1`, `end_date ≥ start_date`). À la
  création, redirige vers le suivi de la demande.
- **`enterprise-requests-page`** : historique paginé, cartes cliquables avec
  pastille de statut (`.bk-status`), ville, participants, dates, budget.
- **`enterprise-request-detail-page`** : récapitulatif de la demande (statut +
  explication, besoins, descriptif) puis les **devis composés** par Kaikun
  (lignes détaillées, sous-total, frais de coordination = marge, total). Quand un
  devis est au statut « envoyé », un bouton **Accepter** déclenche
  `PATCH …/accept` puis recharge la demande. Les devis en **brouillon** (encore en
  préparation côté back-office) ne sont pas montrés.
- **`team-building-status.ts`** : présentation (libellé + tonalité `.bk-status` +
  explication) des statuts de demande et de devis, et libellés des besoins
  (`NEEDS_OPTIONS`).

## Messagerie dans l'espace (conformité cahier §5 « Messages = Tous »)

Le cahier classe l'écran **Messages** comme concernant **tous** les espaces
personnels (« conversation avec le support Kaikun ou le prestataire affecté »).
L'espace entreprise **monte donc les écrans de messagerie** (`../account/messages`)
— pour l'entreprise, c'est le canal de négociation d'un pack.

Ces écrans étaient à l'origine couplés à l'espace client (liens en dur
`/mon-espace/messages`). Ils ont été rendus **autonomes** : le préfixe des liens
est désormais dérivé de `SPACE_CONFIG.basePath`, de sorte qu'un même composant
sert le client (`/mon-espace`) et l'entreprise (`/espace-entreprise`) sans jamais
éjecter l'utilisateur hors de son espace.

## Modèle & service

- [`models/team-building.model.ts`](../../models/team-building.model.ts) — miroirs
  de `TeamBuildingRequestResource` / `TeamBuildingQuoteResource`.
- [`core/api/team-building.service.ts`](../../core/api/team-building.service.ts) —
  `create`, `myRequests`, `get`, `acceptQuote`.

## Notification in-app du devis

Complément backend de F6 : `TeamBuildingQuoteSentNotification` a reçu le canal
`database` (en plus du mail) avec une `action_url` vers
`/espace-entreprise/demandes/{id}`. La cloche et l'écran **Notifications** de
l'espace entreprise préviennent ainsi l'entreprise dès qu'un devis lui est envoyé.
