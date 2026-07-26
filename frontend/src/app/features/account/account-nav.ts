/**
 * Carte de navigation de l'espace client (F3.1).
 *
 * Source unique décrivant les sections de l'espace personnel, partagée par la
 * navigation latérale (`space-layout`) ET les tuiles de la page d'accueil de
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
  | 'user' // profil
  | 'help' // aide (mode d'emploi de l'espace)
  | 'building' // biens immobiliers (espace propriétaire, F4)
  | 'wallet' // reversements / finances (espace propriétaire, F4)
  | 'car' // offres réservables — véhicules & circuits (espace prestataire, F5.6)
  | 'globe' // projets diaspora — pilotés à distance (espace client, F3.8)
  | 'document'; // documents / rapports (espaces pro)

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
    ready: true, // F3.4 ✅
  },
  {
    label: 'Projets diaspora',
    description: 'Vos projets pilotés à distance et leurs rapports de suivi.',
    path: 'diaspora',
    icon: 'globe',
    ready: true, // F3.8 ✅
  },
  {
    label: 'Favoris',
    description: 'Les biens et services que vous avez sauvegardés.',
    path: 'favoris',
    icon: 'heart',
    ready: true, // F3.5 ✅
  },
  {
    label: 'Notifications',
    description: 'Mises à jour de vos demandes, réservations et documents.',
    path: 'notifications',
    icon: 'bell',
    ready: true, // F3.6 ✅
  },
  {
    label: 'Messages',
    description: 'Échangez avec le support Kaikun ou votre prestataire.',
    path: 'messages',
    icon: 'chat',
    ready: true, // F3.7 ✅
  },
  // NB : « Profil » et « Se déconnecter » ne sont PAS dans le menu de gauche :
  // ils sont accessibles depuis le menu utilisateur de l'en-tête (haut droite),
  // ce qui garde le rail court (pas de défilement). De même, « Aide » (mode
  // d'emploi) est rendue dans le PIED du rail (space-layout) à côté de « Retour
  // au site », et n'apparaît pas dans les tuiles de l'accueil.
];
