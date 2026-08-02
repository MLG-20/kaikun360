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

/** Corps envoyé à POST /auth/two-factor (second facteur du back-office, F7.1.d). */
export interface TwoFactorPayload {
  /** Le même identifiant qu'à l'étape login (e-mail ou téléphone). */
  login: string;
  /** Code à 6 chiffres reçu par e-mail. */
  code: string;
}

/**
 * Réponse de POST /auth/login quand le compte est soumis à la double
 * authentification (admin / super_admin) : aucun jeton n'est délivré, un code a
 * été envoyé et le frontend doit résoudre le défi via `two-factor`.
 */
export interface TwoFactorChallenge {
  two_factor_required: true;
  channel: string;
  login: string;
}

/** Résultat normalisé d'une tentative de connexion : session ouverte OU défi 2FA. */
export type LoginOutcome =
  | { kind: 'authenticated'; user: User }
  | { kind: 'two_factor'; login: string; channel: string };

/** Canal de vérification d'un compte (e-mail ou téléphone). */
export type VerificationChannel = 'email' | 'phone';

/**
 * Média par lequel un code de vérification est RÉELLEMENT parti.
 *
 * À distinguer du canal ci-dessus, qui dit ce que le code confirme : vérifier
 * un TÉLÉPHONE ('phone') n'implique pas que le code arrive par SMS. Tant
 * qu'aucun fournisseur SMS n'est branché, le backend le route vers l'adresse
 * e-mail du compte et l'annonce ici (`services.sms.verification_via_mail`).
 */
export type CodeDelivery = 'sms' | 'mail';

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
