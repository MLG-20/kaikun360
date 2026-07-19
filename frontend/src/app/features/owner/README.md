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

Chaque rubrique du rail porte un drapeau `ready` : les rubriques non encore
construites sont affichées « Bientôt » (aucun lien mort), et passeront à
`ready: true` avec leur sous-phase (F4.2 → F4.5).

## Écrans

- **Tableau de bord** (F4.1) — [`overview/owner-overview-page.ts`](overview/owner-overview-page.ts).
  Interroge `GET /manage/dashboard` (via `core/api/manage.service.ts`) et affiche
  les **agrégats réels** de gestion locative du propriétaire connecté (scopés
  côté backend à ses seuls biens) : mandats actifs, loyers encaissés / impayés,
  dépenses, reversements, incidents ouverts. Les indicateurs qui appellent
  l'attention (impayés, incidents) sont teintés en ambre ; les loyers encaissés
  en vert. Suit une grille de **tuiles** vers les rubriques de l'espace.
- **Mes biens** (F4.2 / F4.3), **Gestion locative** (F4.4), **Documents** (F4.5)
  — à venir.
