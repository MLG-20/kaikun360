/**
 * Modèle Utilisateur — miroir de `UserResource` (backend Laravel).
 * Voir `backend/app/Modules/Core/Http/Resources/UserResource.php`.
 */
export interface User {
  id: number;
  name: string;
  email: string | null;
  phone: string | null;
  city: string | null;
  /** Adresse libre (rue/quartier) — F3.2b. */
  address: string | null;
  /** Localisation structurée (F3.2b) : identifiants pour préremplir la cascade. */
  region_id: number | null;
  department_id: number | null;
  commune_id: number | null;
  /** Noms lisibles, présents uniquement quand les relations sont chargées. */
  region?: string | null;
  department?: string | null;
  commune?: string | null;
  status: string | null;
  status_label: string | null;
  /** Noms des rôles Spatie, ex. ["client"]. */
  roles: string[];
  /** Présent uniquement si chargé côté backend (->load('profile')). */
  profile?: Profile;
  email_verified_at: string | null;
  phone_verified_at: string | null;
  created_at: string | null;
}

/**
 * Profil utilisateur — miroir de `ProfileResource`.
 */
export interface Profile {
  type: string | null;
  type_label: string | null;
  verification_status: string | null;
  preferences: Record<string, unknown> | null;
}
