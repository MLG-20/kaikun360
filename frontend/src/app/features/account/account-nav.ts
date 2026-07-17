/**
 * Carte de navigation de l'espace client (F3.1).
 *
 * Source unique décrivant les sections de l'espace personnel, partagée par la
 * navigation latérale (`account-layout`) ET les tuiles de la page d'accueil de
 * l'espace (`account-overview-page`). Chaque section porte un drapeau `ready` :
 *
 *   - `ready: true`  → l'écran est construit, l'entrée est un vrai lien ;
 *   - `ready: false` → l'écran arrive dans une sous-phase suivante (F3.2 → F3.7),
 *     l'entrée est affichée mais **non cliquable** (marquée « Bientôt »).
 *
 * On liste dès maintenant toutes les sections pour donner à l'utilisateur la
 * carte complète de son espace ; on bascule `ready` à `true` au fur et à mesure
 * (aucun lien mort : une section non prête n'est jamais un lien).
 */

/** Clé d'icône SVG (rendue via un `@switch` dans les templates de l'espace). */
export type AccountIcon =
  | 'grid' // tableau de bord
  | 'inbox' // mes demandes
  | 'calendar' // réservations
  | 'heart' // favoris
  | 'bell' // notifications
  | 'chat' // messages
  | 'user'; // profil

/** Une entrée de navigation de l'espace client. */
export interface AccountNavItem {
  /** Libellé affiché. */
  label: string;
  /** Courte description (tuiles de l'accueil de l'espace). */
  description: string;
  /** Chemin relatif à `/mon-espace` (ex. `''` pour l'accueil, `profil`). */
  path: string;
  /** Icône SVG associée. */
  icon: AccountIcon;
  /** L'écran est-il construit ? (sinon affiché « Bientôt », non cliquable). */
  ready: boolean;
}

/**
 * Les sections de l'espace client, dans l'ordre d'affichage. L'accueil
 * (`path: ''`) est toujours prêt ; les autres passeront à `ready: true` avec
 * leur sous-phase respective.
 */
export const ACCOUNT_NAV: readonly AccountNavItem[] = [
  {
    label: 'Tableau de bord',
    description: 'Vue d’ensemble de votre compte et de vos activités.',
    path: '',
    icon: 'grid',
    ready: true,
  },
  {
    label: 'Mes demandes',
    description: 'Suivez le statut de vos demandes (reçu, devis, confirmé…).',
    path: 'demandes',
    icon: 'inbox',
    ready: true, // F3.3 ✅
  },
  {
    label: 'Réservations',
    description: 'Vos nuitées, locations de véhicules et expériences.',
    path: 'reservations',
    icon: 'calendar',
    ready: false, // F3.4
  },
  {
    label: 'Favoris',
    description: 'Les biens et services que vous avez sauvegardés.',
    path: 'favoris',
    icon: 'heart',
    ready: false, // F3.5
  },
  {
    label: 'Notifications',
    description: 'Mises à jour de vos demandes, réservations et documents.',
    path: 'notifications',
    icon: 'bell',
    ready: false, // F3.6
  },
  {
    label: 'Messages',
    description: 'Échangez avec le support Kaikun ou votre prestataire.',
    path: 'messages',
    icon: 'chat',
    ready: false, // F3.7
  },
  {
    label: 'Profil',
    description: 'Identité, téléphone, documents, préférences et sécurité.',
    path: 'profil',
    icon: 'user',
    ready: true, // F3.2 ✅
  },
];
