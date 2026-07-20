import { InjectionToken } from '@angular/core';

import { AccountIcon } from '../../features/account/account-nav';

/**
 * Configuration d'un **espace connecté** (F4) — abstraction du shell app-shell
 * partagée par tous les espaces authentifiés (client, propriétaire, et à venir
 * prestataire / entreprise / back-office).
 *
 * Le layout (`SpaceLayoutComponent`) et son en-tête (`SpaceHeaderComponent`)
 * sont **génériques** : ils ne connaissent pas l'espace qu'ils rendent. Chaque
 * espace fournit sa `SpaceConfig` via le jeton `SPACE_CONFIG` (au niveau des
 * routes de l'espace), ce qui pilote la marque, le titre, la navigation et les
 * liens transverses (cloche de notifications, profil).
 *
 * C'est la généralisation décidée pour F4→F7 : un seul shell, une config par
 * espace (comme `ACCOUNT_NAV` l'était pour le seul espace client).
 */

/** Une entrée de navigation d'un espace (rubrique du rail latéral / tuile d'accueil). */
export interface SpaceNavItem {
  /** Libellé affiché. */
  label: string;
  /** Courte description (tuiles de l'accueil de l'espace). */
  description: string;
  /** Chemin **relatif** à `basePath` (ex. `''` pour l'accueil, `biens`). */
  path: string;
  /** Icône SVG associée (rendue par `app-account-icon`). */
  icon: AccountIcon;
  /** L'écran est-il construit ? (sinon affiché « Bientôt », non cliquable). */
  ready: boolean;
}

/** Description complète d'un espace connecté, injectée dans le shell. */
export interface SpaceConfig {
  /** Préfixe d'URL de l'espace, sans slash final (ex. `/mon-espace`, `/espace-proprietaire`). */
  basePath: string;
  /** Sous-titre de la marque dans le rail (ex. « Espace client »). */
  brandSubtitle: string;
  /** Titre affiché dans la barre supérieure (ex. « Espace propriétaire »). */
  headerTitle: string;
  /** Libellé d'accessibilité de la navigation latérale. */
  navAriaLabel: string;
  /** Les rubriques de l'espace, dans l'ordre d'affichage. */
  nav: readonly SpaceNavItem[];
  /**
   * Chemin **relatif** de la page d'aide, rendue en pied de rail (ex. `aide`).
   * `undefined` → pas de lien d'aide (espaces qui n'en ont pas encore).
   */
  helpPath?: string;
  /**
   * URL **absolue** de la cloche de notifications de l'en-tête.
   *
   * ⚠️ Chaque espace doit pointer vers SON PROPRE écran : un espace ne renvoie
   * jamais vers celui d'un autre, sinon l'utilisateur change de shell sans
   * l'avoir demandé. Les écrans transverses (profil, notifications) sont des
   * composants réutilisés, montés dans les routes de chaque espace.
   */
  notificationsPath: string;
  /** URL **absolue** ciblée par « Mon profil » dans le menu utilisateur. */
  profilePath: string;
}

/** Jeton fournissant la `SpaceConfig` de l'espace courant au shell générique. */
export const SPACE_CONFIG = new InjectionToken<SpaceConfig>('SPACE_CONFIG');
