import { AdminVehicle } from '../../../models/vehicle.model';

/**
 * Contrôle de conformité d'un véhicule — grille partagée par l'onglet Flotte
 * et la fiche du véhicule (F8.2.b).
 *
 * Les exigences diffèrent selon le moyen de transport (cf.
 * `VehicleComplianceChecker`, B7.3) : une **pirogue** relève du fluvial (gilets
 * de sauvetage, aptitude météo, prestataire agréé) tandis qu'un transport
 * **motorisé** relève de l'assurance et de l'identité du chauffeur. D'où deux
 * grilles distinctes plutôt qu'une case unique.
 *
 * Cette table est extraite ici pour que la liste et la fiche ne puissent pas
 * diverger : un véhicule signalé « incomplet » dans la liste doit montrer les
 * mêmes manques une fois ouvert, sinon l'agent ne sait plus qui croire.
 */

/** Verdict global : toutes les pièces, aucune, ou une partie. */
export type ComplianceVerdict = 'ok' | 'ko' | 'partial';

/** Un point de contrôle, avec son verdict individuel. */
export interface ComplianceCheck {
  label: string;
  /** Ce qui est déclaré (référence d'assurance, nom du chauffeur…), si connu. */
  value: string | null;
  passed: boolean;
}

/** La grille applicable au véhicule, point par point. */
export function complianceChecks(v: AdminVehicle): ComplianceCheck[] {
  if (v.type === 'pirogue') {
    const jackets = v.life_jackets_count ?? 0;
    return [
      {
        label: 'Gilets de sauvetage',
        value: jackets ? `${jackets} à bord` : null,
        passed: jackets > 0,
      },
      { label: 'Aptitude météo', value: null, passed: v.weather_compliant === true },
      { label: 'Agrément prestataire', value: null, passed: v.provider_compliant === true },
    ];
  }

  const checks: ComplianceCheck[] = [
    { label: 'Assurance', value: v.insurance_ref, passed: !!v.insurance_ref },
  ];

  // Un véhicule sans chauffeur n'a pas de chauffeur à déclarer : ne pas lui
  // reprocher un manque qui ne le concerne pas.
  if (v.has_driver) {
    checks.push({
      label: 'Identité du chauffeur',
      value: v.driver_identity,
      passed: !!v.driver_identity,
    });
  }

  return checks;
}

/** Verdict global du véhicule. */
export function compliance(v: AdminVehicle): ComplianceVerdict {
  const checks = complianceChecks(v);
  const passed = checks.filter((c) => c.passed).length;

  if (passed === checks.length) return 'ok';
  if (passed === 0) return 'ko';
  return 'partial';
}

/** Libellé du verdict. */
export function complianceLabel(v: AdminVehicle): string {
  switch (compliance(v)) {
    case 'ok':
      return 'Conforme';
    case 'partial':
      return 'Incomplet';
    default:
      return 'Non conforme';
  }
}

/** Classe CSS du badge de conformité. */
export function complianceClass(v: AdminVehicle): string {
  switch (compliance(v)) {
    case 'ok':
      return 'is-ok';
    case 'partial':
      return 'is-warn';
    default:
      return 'is-off';
  }
}

/**
 * Détail des manquements en clair (infobulle de la liste) : l'agent doit savoir
 * QUOI réclamer au prestataire, pas seulement qu'il manque quelque chose.
 */
export function complianceDetail(v: AdminVehicle): string {
  const missing = complianceChecks(v)
    .filter((c) => !c.passed)
    .map((c) => c.label.toLowerCase());

  return missing.length ? 'Manque : ' + missing.join(', ') : 'Toutes les pièces sont déclarées.';
}
