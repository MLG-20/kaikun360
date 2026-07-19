import { SpaceConfig } from '../../layouts/space-layout/space.config';
import { ACCOUNT_NAV } from './account-nav';

/**
 * Configuration de l'**espace client** (F3), pour le shell générique
 * `SpaceLayoutComponent` (F4). Reprend à l'identique le comportement d'avant la
 * généralisation : rail « Espace client », rubriques `ACCOUNT_NAV`, page d'aide,
 * cloche et profil sous `/mon-espace`.
 */
export const CLIENT_SPACE: SpaceConfig = {
  basePath: '/mon-espace',
  brandSubtitle: 'Espace client',
  headerTitle: 'Espace client',
  navAriaLabel: 'Navigation de l’espace client',
  nav: ACCOUNT_NAV,
  helpPath: 'aide',
  notificationsPath: '/mon-espace/notifications',
  profilePath: '/mon-espace/profil',
};
