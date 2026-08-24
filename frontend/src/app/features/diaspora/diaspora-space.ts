import { SpaceConfig, SpaceNavItem } from '../../layouts/space-layout/space.config';

/**
 * Espace **diaspora** (F18, 2026-08-22) — navigation et configuration du shell.
 *
 * Séparé de l'espace client : un compte diaspora pilote son ou ses projets à
 * distance (achat, construction, gestion locative), avec un interlocuteur
 * dédié et des rapports de suivi. Patron directement copié de
 * `owner-space.ts` — même shell générique, même principe d'autonomie.
 *
 * ⚠️ Aucune rubrique « Mes réservations »/« Mes demandes » ici : depuis la
 * séparation, un compte diaspora ne porte QUE le rôle `diaspora` (plus
 * `client`), il n'a donc plus accès à ces écrans, propres à l'espace client.
 */
export const DIASPORA_NAV: readonly SpaceNavItem[] = [
  {
    label: 'Mes projets',
    description: 'Vos projets pilotés à distance et leurs rapports de suivi.',
    path: '',
    icon: 'globe',
    ready: true, // F3.8 ✅
  },
  {
    label: 'Messages',
    description: 'Échangez avec le support Kaikun sur vos projets.',
    path: 'messages',
    icon: 'chat',
    ready: true, // F8.12.c ✅ — le support peut vous faire entrer dans un fil
    badge: 'messages', // F8.13 — pastille de non-lus
  },
  {
    // F11.4/F11.5 — Placée EN DERNIER, et volontairement : une corbeille n'est
    // pas un lieu de travail quotidien, c'est un filet de sécurité.
    label: 'Corbeille',
    description: 'Ce que vous avez retiré de vos listes, récupérable 30 jours.',
    path: 'corbeille',
    icon: 'trash',
    ready: true,
  },
];

/** Configuration de l'espace diaspora pour le shell générique (F18). */
export const DIASPORA_SPACE: SpaceConfig = {
  basePath: '/espace-diaspora',
  brandSubtitle: 'Espace diaspora',
  headerTitle: 'Espace diaspora',
  navAriaLabel: 'Navigation de l’espace diaspora',
  nav: DIASPORA_NAV,
  // Pas encore de page d'aide dédiée à cet espace.
  helpPath: undefined,
  // ⚠️ Chaque espace est AUTONOME : ces liens restent dans l'espace diaspora,
  // jamais vers `/mon-espace/...` (même piège corrigé en F4.1 pour l'espace
  // propriétaire).
  notificationsPath: '/espace-diaspora/notifications',
  profilePath: '/espace-diaspora/profil',
};
