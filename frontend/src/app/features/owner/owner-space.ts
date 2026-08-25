import { SpaceConfig, SpaceNavItem } from '../../layouts/space-layout/space.config';

/**
 * Espace **propriétaire** (F4) — navigation et configuration du shell.
 *
 * Le propriétaire dépose et gère ses biens, suit sa gestion locative (loyers,
 * reversements, incidents) et ses documents. Comme pour l'espace client, on
 * liste dès maintenant toutes les rubriques avec un drapeau `ready` : seules les
 * rubriques construites sont cliquables (les autres, marquées « Bientôt »,
 * passeront à `ready: true` avec leur sous-phase F4.2 → F4.5). Aucun lien mort.
 *
 * Notifications et profil sont propres à l'utilisateur (pas à l'espace) : la
 * cloche et « Mon profil » de l'en-tête pointent, pour l'instant, vers les
 * écrans déjà en place de l'espace client (`/mon-espace/...`).
 */
export const OWNER_NAV: readonly SpaceNavItem[] = [
  {
    label: 'Tableau de bord',
    description: 'Vue d’ensemble de votre gestion locative (loyers, reversements, incidents).',
    path: '',
    icon: 'grid',
    ready: true, // F4.1 ✅
  },
  {
    label: 'Mes biens',
    description: 'Déposez, modifiez vos biens et suivez la validation de vos annonces.',
    path: 'biens',
    icon: 'building',
    ready: true, // F4.2 ✅ (liste + fiche) · F4.3 ✅ (dépôt/édition + mode de location)
  },
  {
    label: 'Gestion locative',
    description: 'Mandats, loyers, reversements et rapports mensuels.',
    path: 'gestion-locative',
    icon: 'wallet',
    ready: true, // F4.4 ✅ (mandats + loyers/reversements/incidents + rapport mensuel)
  },
  {
    label: 'Messages',
    description: 'Échangez avec le support Kaikun sur vos biens et vos locataires.',
    path: 'messages',
    icon: 'chat',
    ready: true, // F8.12.c ✅ — le support peut vous faire entrer dans un fil
    badge: 'messages', // F8.13 — pastille de non-lus
  },
  {
    label: 'Documents',
    description: 'Les pièces justificatives de chacun de vos biens.',
    path: 'documents',
    icon: 'document',
    ready: true, // F4.5 ✅ (documents par bien : dépôt / téléchargement / suppression)
  },
  {
    label: 'Mes reversements',
    description: 'Ce que Kaikun vous doit, et l’historique de ce qui vous a déjà été versé.',
    path: 'reversements',
    icon: 'receipt',
    ready: true, // self-service après F8.16.a ✅
  },
  {
    // F11.4 — Placée EN DERNIER, et volontairement : une corbeille n'est pas
    // un lieu de travail quotidien, c'est un filet de sécurité. La hisser dans
    // le menu la mettrait au même rang que les rubriques métier.
    label: 'Corbeille',
    description: 'Ce que vous avez retiré de vos listes, récupérable 30 jours.',
    path: 'corbeille',
    icon: 'trash',
    ready: true, // F11.4 ✅
  },
];

/** Configuration de l'espace propriétaire pour le shell générique (F4). */
export const OWNER_SPACE: SpaceConfig = {
  basePath: '/espace-proprietaire',
  brandSubtitle: 'Espace propriétaire',
  headerTitle: 'Espace propriétaire',
  navAriaLabel: 'Navigation de l’espace propriétaire',
  nav: OWNER_NAV,
  // Pas encore de page d'aide dédiée à cet espace (F4.5+).
  helpPath: undefined,
  // ⚠️ Chaque espace est AUTONOME : ces liens restent dans l'espace propriétaire.
  // (Ils pointaient vers `/mon-espace/...` en F4.1 — un propriétaire cliquant
  // « Mon profil » était alors éjecté dans l'espace client.)
  notificationsPath: '/espace-proprietaire/notifications',
  profilePath: '/espace-proprietaire/profil',
};
