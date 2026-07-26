import {
  TeamBuildingQuoteStatus,
  TeamBuildingRequestStatus,
} from '../../../models/team-building.model';

/**
 * Présentation des statuts de l'espace entreprise (F6) — demandes et devis de
 * team building. Le backend renvoie déjà un `status_label` français ; on ajoute
 * ici la **tonalité** (pastille globale `.bk-status[data-tone=…]`, partiel
 * `styles/_account.scss`) et une **phrase d'explication**, pour un suivi lisible
 * cohérent avec les autres espaces.
 *
 * Tonalités disponibles : `pending` (or, en attente), `ok` (info, action
 * attendue de l'entreprise), `active` (vert, confirmé), `done` (gris, clos),
 * `cancelled` (rouge, annulé/refusé).
 */
export type StatusTone = 'pending' | 'ok' | 'active' | 'done' | 'cancelled';

interface StatusPresentation {
  readonly label: string;
  readonly tone: StatusTone;
  readonly hint: string;
}

/** Présentation d'un statut de **demande** (miroir de `TeamBuildingRequestStatus`). */
const REQUEST_MAP: Record<TeamBuildingRequestStatus, StatusPresentation> = {
  nouveau: {
    label: 'Nouvelle',
    tone: 'pending',
    hint: 'Votre demande a bien été reçue. Kaikun va l’étudier et composer une proposition.',
  },
  en_etude: {
    label: 'En étude',
    tone: 'pending',
    hint: 'Kaikun compose votre pack sur mesure (lieu, hébergement, activités…).',
  },
  devis_envoye: {
    label: 'Devis envoyé',
    tone: 'ok',
    hint: 'Un devis vous a été envoyé : consultez-le ci-dessous et acceptez-le pour lancer l’organisation.',
  },
  accepte: {
    label: 'Acceptée',
    tone: 'active',
    hint: 'Vous avez accepté le devis. Kaikun coordonne les prestataires et le programme.',
  },
  annule: {
    label: 'Annulée',
    tone: 'cancelled',
    hint: 'Cette demande a été annulée.',
  },
};

/** Présentation d'un statut de **devis** (miroir de `TeamBuildingQuoteStatus`). */
const QUOTE_MAP: Record<TeamBuildingQuoteStatus, StatusPresentation> = {
  brouillon: {
    label: 'En préparation',
    tone: 'pending',
    hint: 'Ce devis est en cours de préparation par Kaikun.',
  },
  envoye: {
    label: 'À accepter',
    tone: 'ok',
    hint: 'Ce devis vous est proposé. Vous pouvez l’accepter.',
  },
  accepte: {
    label: 'Accepté',
    tone: 'active',
    hint: 'Vous avez accepté ce devis.',
  },
  refuse: {
    label: 'Refusé',
    tone: 'cancelled',
    hint: 'Ce devis a été refusé.',
  },
};

const UNKNOWN: StatusPresentation = { label: 'Statut inconnu', tone: 'done', hint: '' };

/** Présentation (libellé + tonalité + explication) d'un statut de demande. */
export function requestStatus(status: TeamBuildingRequestStatus | null): StatusPresentation {
  return (status && REQUEST_MAP[status]) || UNKNOWN;
}

/** Présentation (libellé + tonalité + explication) d'un statut de devis. */
export function quoteStatus(status: TeamBuildingQuoteStatus | null): StatusPresentation {
  return (status && QUOTE_MAP[status]) || UNKNOWN;
}

/**
 * Libellés français des **besoins** d'une demande (`needs`) — miroir des
 * catégories `QuoteLineCategory` proposées à l'entreprise (hors `lieu`, déduit
 * de la ville). Sert à la fois au formulaire et à l'affichage récapitulatif.
 */
export const NEEDS_OPTIONS: readonly { key: string; label: string }[] = [
  { key: 'hebergement', label: 'Hébergement' },
  { key: 'restauration', label: 'Restauration' },
  { key: 'activite', label: 'Activités' },
  { key: 'mobilite', label: 'Transport' },
  { key: 'animation', label: 'Animation' },
];
