/**
 * Notification « base de données » — miroir de `NotificationResource` (F3.6).
 *
 * Chaque notification est une ligne de la table `notifications` (canal
 * `database` de Laravel), projetée dans un format stable par le backend : une
 * `category` (pour l'icône/teinte), un `title` et un `body` lisibles, un
 * `action_url` optionnel (route interne vers l'écran concerné), et l'état de
 * lecture exposé en booléen `read` (l'horodatage `read_at` reste disponible).
 */

/**
 * Catégorie fonctionnelle d'une notification. Détermine l'icône et la teinte
 * affichées ; miroir des `category` produites par les `toArray()` backend.
 * `general` est le repli par défaut pour toute catégorie inconnue.
 */
export type NotificationCategory = 'request' | 'quote' | 'booking' | 'message' | 'general';

export interface AppNotification {
  /** UUID de la notification (identifiant de la ligne `notifications`). */
  id: string;
  /** Catégorie fonctionnelle (icône/teinte). */
  category: NotificationCategory;
  /** Titre court. */
  title: string;
  /** Corps du message. */
  body: string;
  /** Route interne vers l'écran concerné (ou null si aucune). */
  action_url: string | null;
  /** La notification a-t-elle été lue ? */
  read: boolean;
  /** Horodatage de lecture (null tant que non lue). */
  read_at: string | null;
  /**
   * F11.5 — peut-elle être rangée dans la corbeille ? Décidé par le serveur :
   * on ne range qu'une notification DÉJÀ LUE.
   */
  hideable?: boolean;
  /** Horodatage de création. */
  created_at: string | null;
}
