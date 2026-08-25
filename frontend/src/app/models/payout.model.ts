/**
 * Reversements aux partenaires (F8.16.a) — miroirs de `PartnerDueResource` et
 * `PartnerPayoutResource` (back-office).
 *
 * ⚠️ **Deux notions à ne pas confondre**, et c'est toute la conception :
 *   - une **dette** (`PartnerDue`) est ce que Kaikun doit pour UN service rendu ;
 *   - un **versement** (`PartnerPayout`) est UN virement, qui en solde plusieurs.
 *
 * C'est le lot qui rend la cadence libre — hebdomadaire, mensuelle, à la
 * demande — sans rien changer au modèle : un virement par réservation coûterait
 * des frais à chaque nuit vendue.
 */

/** Le partenaire à qui l'argent est dû (propriétaire ou prestataire). */
export interface PayoutBeneficiary {
  id: number | null;
  name: string | null;
  email: string | null;
  phone: string | null;
}

/** Une dette envers un partenaire, née d'un service rendu. */
export interface PartnerDue {
  id: number;
  reference: string;
  beneficiary: PayoutBeneficiary;
  /**
   * D'où vient la dette. Le `label` est composé par le SERVEUR : une nuitée, un
   * circuit et une mission ne se nomment pas au même endroit, et l'écran n'a pas
   * à connaître ces cinq cas.
   */
  source: {
    type: string;
    id: number;
    reference: string | null;
    label: string | null;
  };
  /** Assiette — ⚠️ hors caution : elle n'a jamais appartenu au partenaire. */
  gross_xof: number;
  /** Commission Kaikun, **recopiée figée** depuis la source, jamais recalculée. */
  commission_xof: number;
  net_xof: number;
  status: 'en_attente' | 'exigible' | 'payee' | 'annulee';
  status_label: string;
  eligible_at: string | null;
  /**
   * ⚠️ Décidé par le SERVEUR (miroir du scope `payables()`). L'écran ne rejoue
   * pas « exigible ET sans lot » : une règle d'argent ne vit qu'à un endroit.
   */
  is_payable: boolean;
  payout_id: number | null;
  cancelled_reason: string | null;
  cancelled_at: string | null;
  created_at: string | null;
}

/** Un versement effectué (ou à exécuter) au profit d'un partenaire. */
export interface PartnerPayout {
  id: number;
  reference: string;
  beneficiary: PayoutBeneficiary;
  amount_xof: number;
  status: 'en_attente' | 'paye' | 'echoue';
  status_label: string;
  method: string | null;
  external_reference: string | null;
  paid_at: string | null;
  note: string | null;
  /**
   * ⚠️ Le chemin de stockage n'est JAMAIS servi : un justificatif de virement
   * porte des coordonnées. `proof_url` est une URL **signée de 10 minutes**, à
   * poser en `[href]` — jamais à appeler via HttpClient (la signature vaut pour
   * une requête de navigateur, pas pour un appel authentifié).
   */
  has_proof: boolean;
  proof_original_name: string | null;
  proof_url: string | null;
  created_by: string | null;
  paid_by: string | null;
  created_at: string | null;
  dues?: PartnerDue[];
  dues_count?: number;
}

/**
 * Une ligne de l'écran d'entrée : ce qu'on doit à UN partenaire, tous services
 * confondus. Agrégée côté serveur — on ne vire pas à une réservation, on vire à
 * quelqu'un.
 */
export interface PayoutBeneficiaryLine {
  beneficiary: PayoutBeneficiary;
  /** Payable aujourd'hui (délai de sûreté écoulé). */
  payable_xof: number;
  /** Acquis mais encore sous délai — à ne PAS virer. */
  pending_xof: number;
  dues_count: number;
  oldest_eligible_at: string | null;
}

/** Totaux de l'écran d'entrée. */
export interface PayoutTotals {
  payable_xof: number;
  pending_xof: number;
  beneficiaries_count: number;
}

/**
 * « Mes reversements » — miroirs de `PartnerDueSelfResource` /
 * `PartnerPayoutSelfResource`, la vue self-service du même registre.
 *
 * ⚠️ Sans `beneficiary` (c'est moi) ni `commission_xof` (ce que Kaikun
 * retient — pas mon affaire) : ce ne sont pas les mêmes champs que
 * `PartnerDue`/`PartnerPayout`, qui restent réservés au back-office.
 */
export interface PartnerDueSelf {
  id: number;
  reference: string;
  source: {
    type: string;
    label: string | null;
  };
  gross_xof: number;
  net_xof: number;
  status: 'en_attente' | 'exigible' | 'payee' | 'annulee';
  status_label: string;
  eligible_at: string | null;
  created_at: string | null;
}

/** Un versement déjà reçu (ou en cours), vu du partenaire lui-même. */
export interface PartnerPayoutSelf {
  id: number;
  reference: string;
  amount_xof: number;
  status: 'en_attente' | 'paye' | 'echoue';
  status_label: string;
  method: string | null;
  paid_at: string | null;
  /** Même règle que `PartnerPayout.proof_url` : `[href]` brut, jamais HttpClient. */
  has_proof: boolean;
  proof_original_name: string | null;
  proof_url: string | null;
  created_at: string | null;
}
