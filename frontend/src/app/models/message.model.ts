/**
 * Messagerie de l'espace client (F3.7) — miroirs de `ConversationResource` et
 * `MessageResource` côté backend.
 *
 * Le socle est GÉNÉRIQUE : une conversation regroupe des participants et une
 * suite de messages. Du point de vue de l'utilisateur courant, le backend
 * expose déjà les AUTRES participants (`counterparts`), le drapeau `is_mine`
 * (pour aligner les bulles) et le nombre de messages non lus — le frontend n'a
 * donc pas à connaître son propre identifiant.
 */

/** Un correspondant (autre participant d'une conversation), du point de vue courant. */
export interface Counterpart {
  /** Identifiant du compte. */
  id: number;
  /** Nom affiché. */
  name: string;
}

/** Aperçu du dernier message d'une conversation (pour la liste des fils). */
export interface LastMessagePreview {
  /** Corps tronqué. */
  body: string;
  /** Ce dernier message a-t-il été émis par l'utilisateur courant ? */
  is_mine: boolean;
  /** Horodatage de création. */
  created_at: string | null;
}

/** Un message d'une conversation. */
export interface ConversationMessage {
  /** Identifiant du message. */
  id: number;
  /** Corps (texte brut, échappé à l'affichage). */
  body: string;
  /** Auteur (id + nom éventuel si chargé). */
  sender: { id: number; name: string | null };
  /** Ce message a-t-il été émis par l'utilisateur courant ? (alignement des bulles). */
  is_mine: boolean;
  /** Horodatage de création. */
  created_at: string | null;
}

/**
 * Une conversation. Selon le contexte, `messages` n'est présent que sur le
 * détail d'un fil (écran de conversation) ; la liste ne porte que l'aperçu
 * `last_message`.
 */
export interface Conversation {
  /** Identifiant du fil. */
  id: number;
  /** Sujet libre (ou null). */
  subject: string | null;
  /** Étiquette du contexte rattaché (« Demande », « Réservation »…), ou null. */
  context_label: string | null;
  /** Les autres participants (le correspondant, du point de vue courant). */
  counterparts: Counterpart[];
  /** Aperçu du dernier message (liste des fils), ou null si aucun. */
  last_message: LastMessagePreview | null;
  /** Messages du fil, du plus ancien au plus récent (détail uniquement). */
  messages?: ConversationMessage[];
  /** Nombre de messages non lus par l'utilisateur courant. */
  unread_count: number;
  /** Horodatage du dernier message (tri par activité). */
  last_message_at: string | null;
  /** Horodatage de création du fil. */
  created_at: string | null;
}
