import { User } from '../../models/user.model';

/** Corps envoyé à POST /auth/login (identifiant = e-mail OU téléphone). */
export interface LoginPayload {
  login: string;
  password: string;
}

/** Corps envoyé à POST /auth/register (miroir de RegisterRequest). */
export interface RegisterPayload {
  name: string;
  email: string;
  phone?: string;
  city?: string;
  password: string;
  password_confirmation: string;
  /** Type de profil : client, proprietaire, prestataire, diaspora, entreprise. */
  profile_type: string;
}

/** Corps envoyé à POST /auth/google (ID token Google Identity Services). */
export interface GooglePayload {
  id_token: string;
}

/** Canal de vérification d'un compte (e-mail ou téléphone). */
export type VerificationChannel = 'email' | 'phone';

/** Corps envoyé à POST /auth/password/reset (miroir du contrôleur). */
export interface ResetPasswordPayload {
  login: string;
  code: string;
  password: string;
  password_confirmation: string;
}

/** Données renvoyées par login/register/google (dans l'enveloppe `data`). */
export interface AuthResult {
  user: User;
  token: string;
}
