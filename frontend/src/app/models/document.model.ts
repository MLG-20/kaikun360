/**
 * Modèle Pièce justificative — miroir de `UserDocumentResource` (backend Laravel).
 * Voir `backend/app/Modules/Core/Http/Resources/UserDocumentResource.php`.
 *
 * Le chemin de stockage réel n'est jamais exposé : le backend fournit à la place
 * une `download_url` **signée et temporaire** (valable 10 minutes), seule façon
 * d'accéder au fichier privé.
 */
export interface UserDocument {
  id: number;
  /** Valeur de l'enum `DocumentType` (ex. `cni`, `passeport`…). */
  type: string | null;
  /** Libellé lisible du type (ex. « Carte nationale d'identité »). */
  type_label: string | null;
  original_name: string | null;
  mime_type: string | null;
  /** Taille du fichier en octets. */
  size: number | null;
  /** Statut de vérification de la pièce côté back-office (ex. `en_attente`). */
  status: string | null;
  /** URL de téléchargement signée et temporaire (10 min). */
  download_url: string;
  created_at: string | null;
}

/**
 * Types de pièces déposables — miroir de l'enum `DocumentType` (backend).
 * Voir `backend/app/Modules/Core/Enums/DocumentType.php`. Source unique pour le
 * menu déroulant de dépôt : `value` = valeur envoyée à l'API, `label` = affiché.
 */
export interface DocumentTypeOption {
  value: string;
  label: string;
}

export const DOCUMENT_TYPES: readonly DocumentTypeOption[] = [
  { value: 'cni', label: "Carte nationale d'identité" },
  { value: 'passeport', label: 'Passeport' },
  { value: 'permis_conduire', label: 'Permis de conduire' },
  { value: 'justificatif_domicile', label: 'Justificatif de domicile' },
  { value: 'registre_commerce', label: 'Registre de commerce' },
  { value: 'autre', label: 'Autre' },
];
