import { SpaceConfig, SpaceNavItem } from '../../layouts/space-layout/space.config';

/**
 * Espace **prestataire** (F5) — navigation et configuration du shell.
 *
 * Le prestataire dépose ses services (avec certifications), gère ses
 * disponibilités, suit les missions qui lui sont affectées, ses avis et ses
 * revenus/commissions. Comme les espaces client (F3) et propriétaire (F4), on
 * liste dès maintenant toutes les rubriques avec un drapeau `ready` : seules
 * les rubriques construites sont cliquables (les autres, marquées « Bientôt »,
 * passeront à `ready: true` avec leur sous-phase F5.2 → F5.5). Aucun lien mort.
 *
 * Notifications et profil sont montés DANS l'espace prestataire (chaque espace
 * est autonome : cliquer « Mon profil » ne doit jamais éjecter le prestataire
 * vers un autre espace — cf. espace propriétaire F4).
 */
export const PROVIDER_NAV: readonly SpaceNavItem[] = [
  {
    label: 'Tableau de bord',
    description: 'Statut de votre dossier, note moyenne et certifications en un coup d’œil.',
    path: '',
    icon: 'grid',
    ready: true, // F5.1 ✅
  },
  {
    label: 'Mes services',
    description: 'Décrivez vos prestations et déposez vos documents de certification.',
    path: 'services',
    icon: 'building',
    ready: false, // F5 (dépôt de service + certifications)
  },
  {
    label: 'Disponibilités',
    description: 'Indiquez vos créneaux et périodes d’indisponibilité.',
    path: 'disponibilites',
    icon: 'calendar',
    ready: false, // F5.4
  },
  {
    label: 'Missions reçues',
    description: 'Acceptez, refusez et suivez l’avancement des missions qui vous sont confiées.',
    path: 'missions',
    icon: 'inbox',
    ready: true, // F5.2 ✅
  },
  {
    label: 'Avis reçus',
    description: 'Consultez les avis et la notation laissés par vos clients.',
    path: 'avis',
    icon: 'chat',
    ready: false, // F5.5
  },
  {
    label: 'Revenus & commissions',
    description: 'Suivez vos gains et les commissions prélevées par Kaikun.',
    path: 'revenus',
    icon: 'wallet',
    ready: true, // F5.3 ✅
  },
];

/** Configuration de l'espace prestataire pour le shell générique (F5). */
export const PROVIDER_SPACE: SpaceConfig = {
  basePath: '/espace-prestataire',
  brandSubtitle: 'Espace prestataire',
  headerTitle: 'Espace prestataire',
  navAriaLabel: 'Navigation de l’espace prestataire',
  nav: PROVIDER_NAV,
  // Pas encore de page d'aide dédiée à cet espace.
  helpPath: undefined,
  // ⚠️ Chaque espace est AUTONOME : ces liens restent dans l'espace prestataire.
  notificationsPath: '/espace-prestataire/notifications',
  profilePath: '/espace-prestataire/profil',
};
