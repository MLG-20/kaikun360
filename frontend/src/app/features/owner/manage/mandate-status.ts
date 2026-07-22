import { Mandate } from '../../../models/manage.model';

/**
 * Présentation des **statuts de gestion locative** pour l'espace propriétaire
 * (F4.4). Le backend fournit déjà les libellés français (`status_label`) de
 * chaque ligne ; on n'a donc besoin ICI que de la **tonalité** de la pastille
 * globale `.bk-status[data-tone=…]` (partiel `styles/_account.scss`) :
 *
 *   - `active`    (vert)  → sain / terminé favorablement (mandat actif, loyer payé…) ;
 *   - `pending`   (or)    → en attente / à surveiller (signature, reversement en attente…) ;
 *   - `cancelled` (rouge) → à traiter (loyer impayé, incident ouvert) ;
 *   - `done`      (gris)  → clos / neutre (mandat terminé, incident clos).
 *
 * Chaque table est le miroir de l'enum backend correspondant. Un statut inconnu
 * retombe sur `done` (neutre) plutôt que de casser l'affichage.
 */
export type StatusTone = 'active' | 'pending' | 'cancelled' | 'done' | 'ok';

/** Tonalité d'un statut de MANDAT (`MandateStatus`). */
export function mandateTone(status: string | null): StatusTone {
  switch (status) {
    case 'actif':
      return 'active';
    case 'en_attente':
      return 'pending';
    case 'suspendu':
    case 'termine':
      return 'done';
    default:
      return 'done';
  }
}

/** Tonalité d'une échéance de LOYER (`RentStatus`). */
export function rentTone(status: string | null): StatusTone {
  switch (status) {
    case 'paye':
      return 'active';
    case 'en_retard':
      return 'pending';
    case 'impaye':
      return 'cancelled';
    default:
      return 'done';
  }
}

/** Tonalité d'un REVERSEMENT au propriétaire (`OwnerPayoutStatus`). */
export function payoutTone(status: string | null): StatusTone {
  switch (status) {
    case 'effectue':
      return 'active';
    case 'en_attente':
      return 'pending';
    default:
      return 'done';
  }
}

/** Tonalité d'un INCIDENT (`IncidentStatus`). */
export function incidentTone(status: string | null): StatusTone {
  switch (status) {
    case 'resolu':
      return 'active';
    case 'en_cours':
      return 'pending';
    case 'ouvert':
      return 'cancelled';
    case 'clos':
      return 'done';
    default:
      return 'done';
  }
}

/**
 * Libellé du BIEN rattaché au mandat : titre du bien, à défaut sa référence de
 * mandat. Sert de titre à la carte / fiche quand le bien est chargé.
 */
export function mandatePropertyTitle(mandate: Mandate): string {
  return mandate.property?.title || mandate.reference || `Mandat #${mandate.id}`;
}

/** Localité lisible du bien géré : commune → département → région, sinon null. */
export function mandateLocality(mandate: Mandate): string | null {
  const l = mandate.property?.location;
  return l?.commune || l?.department || l?.region || null;
}
