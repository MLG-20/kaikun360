import { AdminExperience } from '../../../models/experience.model';

/**
 * Le **programme** d'un circuit — lecture partagée par l'onglet Circuits et la
 * fiche du circuit (F8.2.c).
 *
 * Ce que le cahier des charges appelle « programme », le modèle le stocke en
 * `inclusions` : les prestations comprises dans le prix (guide, restauration,
 * transport…).
 *
 * ⚠️ Deux formes cohabitent en base, et il faut vivre avec les deux : le backend
 * renvoie un **tableau vide** quand rien n'est renseigné, et un **objet
 * `{ clé: booléen }`** sinon. Un circuit dont le programme est saisi mais lu
 * comme un tableau apparaîtrait « non renseigné » — d'où cette lecture unique,
 * pour que la liste et la fiche disent la même chose.
 */

/** Libellé lisible d'une clé d'inclusion (repli : la clé telle quelle). */
function inclusionLabel(key: string): string {
  switch (key) {
    case 'restauration':
      return 'Restauration';
    case 'guide':
      return 'Guide';
    case 'transport':
      return 'Transport';
    case 'hebergement':
      return 'Hébergement';
    default:
      return key;
  }
}

/** Les inclusions ACTIVES du circuit, en libellés lisibles. */
export function programmeOf(circuit: AdminExperience): string[] {
  const inclusions = circuit.inclusions;
  if (!inclusions || Array.isArray(inclusions)) return [];

  return Object.entries(inclusions)
    .filter(([, included]) => included === true)
    .map(([key]) => inclusionLabel(key));
}
