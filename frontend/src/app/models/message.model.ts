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
  /**
   * Rôle lisible (F8.12.c) — « Support Kaikun », « Propriétaire »,
   * « Prestataire ». Depuis qu'un professionnel peut entrer dans le fil, savoir
   * à QUI l'on parle change la réponse qu'on écrit. ⚠️ Le rôle et le nom sont
   * exposés, **jamais les coordonnées** : celles écrites dans les messages sont
   * elles-mêmes masquées entre non-staff, côté serveur.
   */
  role?: string | null;
  /** Ce correspondant fait-il partie de l'équipe Kaikun ? */
  is_team?: boolean;
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
  /**
   * Agent Kaikun RESPONSABLE du fil (F8.12), ou null tant qu'aucun n'a été
   * assigné. C'est le nom que le client voit en face de lui : un interlocuteur
   * nommé, comme pour le devis en F8.11.
   */
  assigned_agent?: { id: number; name: string } | null;
  /** Le fil a-t-il été clos par l'équipe ? (écrire à nouveau le rouvre). */
  is_closed?: boolean;
  /** Horodatage de clôture, ou null. */
  closed_at?: string | null;
  /** Les autres participants (le correspondant, du point de vue courant). */
  counterparts: Counterpart[];
  /** Aperçu du dernier message (liste des fils), ou null si aucun. */
  last_message: LastMessagePreview | null;
  /** Messages du fil, du plus ancien au plus récent (détail uniquement). */
  messages?: ConversationMessage[];
  /** Nombre de messages non lus par l'utilisateur courant. */
  unread_count: number;
  /**
   * F11.5 — ce participant peut-il ranger le fil dans sa corbeille ? Décidé par
   * le serveur (fil entièrement lu). ⚠️ Ne pas le recalculer depuis
   * `unread_count` : deux calculs qui divergent d'une seconde donneraient un
   * bouton qui refuse un fil affiché comme lu.
   */
  hideable?: boolean;
  /** Horodatage du dernier message (tri par activité). */
  last_message_at: string | null;
  /** Horodatage de création du fil. */
  created_at: string | null;
}
