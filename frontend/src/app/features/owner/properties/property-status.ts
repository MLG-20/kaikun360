import { Property } from '../../../models/property.model';

/**
 * Présentation du **statut de validation** d'un bien pour l'espace propriétaire
 * (F4.2). Le backend renvoie un statut brut (`properties.status`) sans libellé ;
 * on le traduit ici en libellé français et en **tonalité** réutilisant la
 * pastille globale `.bk-status[data-tone=…]` (partiel `styles/_account.scss`) :
 *
 *   - `active`    (vert)  → bien PUBLIÉ, visible au catalogue ;
 *   - `pending`   (or)    → EN ATTENTE de validation par un agent ;
 *   - `cancelled` (rouge) → REJETÉ (dossier à revoir) ;
 *   - `done`      (gris)  → SUSPENDU ou ARCHIVÉ (retiré du catalogue).
 *
 * Miroir de `App\Modules\Immo\Enums\PropertyStatus::label()`. Un statut inconnu
 * retombe sur un affichage neutre plutôt que de casser l'écran.
 */
export type PropertyStatusTone = 'active' | 'pending' | 'cancelled' | 'done';

interface StatusPresentation {
  readonly label: string;
  readonly tone: PropertyStatusTone;
  /** Phrase courte expliquant ce que le statut implique (affichée sur la fiche). */
  readonly hint: string;
}

const STATUS_MAP: Record<string, StatusPresentation> = {
  publie: {
    label: 'Publié',
    tone: 'active',
    hint: 'Votre bien est en ligne et visible au catalogue public.',
  },
  en_attente_validation: {
    label: 'En attente de validation',
    tone: 'pending',
    hint: 'Un agent Kaikun 360 vérifie votre annonce avant sa mise en ligne.',
  },
  rejete: {
    label: 'Rejeté',
    tone: 'cancelled',
    hint: 'Votre annonce n’a pas été validée. Contactez le support pour connaître le motif.',
  },
  suspendu: {
    label: 'Suspendu',
    tone: 'done',
    hint: 'Votre bien a été temporairement retiré du catalogue.',
  },
  archive: {
    label: 'Archivé',
    tone: 'done',
    hint: 'Votre bien est archivé et n’apparaît plus au catalogue.',
  },
};

/** Présentation par défaut si le statut est absent ou inconnu. */
const UNKNOWN: StatusPresentation = {
  label: 'Statut inconnu',
  tone: 'done',
  hint: 'Statut non renseigné.',
};

/** Renvoie la présentation (libellé + tonalité + explication) d'un statut brut. */
export function propertyStatus(status: string | null): StatusPresentation {
  return (status && STATUS_MAP[status]) || UNKNOWN;
}

/**
 * Localité lisible d'un bien : commune, à défaut département, à défaut région.
 * Renvoie `null` si aucune n'est renseignée (évite d'afficher une ligne vide).
 */
export function propertyLocality(property: Property): string | null {
  const l = property.location;
  return l.commune || l.department || l.region || null;
}

/**
 * Le bien porte-t-il un **badge de confiance** ? On suit la convention du
 * catalogue public (`verifiedBadge`) : `unverified`/`aucun` = pas de badge ; tout
 * autre niveau vaut « Vérifié ». Évite d'exposer une valeur technique brute au
 * propriétaire.
 */
export function propertyVerified(level: string | null): boolean {
  return !!level && level !== 'unverified' && level !== 'aucun';
}
