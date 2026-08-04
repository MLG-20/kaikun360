import { SpaceConfig, SpaceNavItem } from '../../layouts/space-layout/space.config';

/**
 * Espace **entreprise** (F6) — navigation et configuration du shell.
 *
 * Destiné aux entreprises, ONG, écoles et institutions (rôle `entreprise`), il
 * couvre le besoin du cahier §5/§7 : organiser des demandes groupées de team
 * building, suivre les devis composés par Kaikun et l'historique des commandes.
 *
 * Trois rubriques, toutes construites en F6 :
 *   - Tableau de bord : accueil de l'espace + accès à une nouvelle demande ;
 *   - Mes demandes : historique et suivi des demandes/devis groupe ;
 *   - Messages : conversation avec le support Kaikun (cahier §5 « Messages =
 *     Tous » — canal de négociation d'un pack). Écrans de messagerie génériques
 *     réutilisés (les mêmes que l'espace client), rendus autonomes par
 *     `SPACE_CONFIG`.
 *
 * Comme les autres espaces, Notifications et Profil sont des écrans transverses
 * montés DANS l'espace (l'en-tête y renvoie), pour ne jamais éjecter
 * l'entreprise vers un autre espace.
 */
export const ENTERPRISE_NAV: readonly SpaceNavItem[] = [
  {
    label: 'Tableau de bord',
    description: 'Vue d’ensemble de vos demandes groupées et accès à une nouvelle demande.',
    path: '',
    icon: 'grid',
    ready: true, // F6 ✅
  },
  {
    label: 'Mes demandes',
    description: 'Suivez vos demandes de team building et les devis composés par Kaikun.',
    path: 'demandes',
    icon: 'inbox',
    ready: true, // F6 ✅ (historique + détail + acceptation de devis)
  },
  {
    label: 'Messages',
    description: 'Échangez avec le support Kaikun pour organiser votre événement.',
    path: 'messages',
    icon: 'chat',
    ready: true, // F6 ✅ (messagerie générique, cahier §5 « Tous »)
    badge: 'messages', // F8.13 — pastille de non-lus
  },
];

/** Configuration de l'espace entreprise pour le shell générique (F6). */
export const ENTERPRISE_SPACE: SpaceConfig = {
  basePath: '/espace-entreprise',
  brandSubtitle: 'Espace entreprise',
  headerTitle: 'Espace entreprise',
  navAriaLabel: 'Navigation de l’espace entreprise',
  nav: ENTERPRISE_NAV,
  // Pas de page d'aide dédiée à cet espace pour l'instant.
  helpPath: undefined,
  // ⚠️ Chaque espace est AUTONOME : ces liens restent dans l'espace entreprise.
  notificationsPath: '/espace-entreprise/notifications',
  profilePath: '/espace-entreprise/profil',
};
