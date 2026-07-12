/**
 * Enveloppe standard des réponses de succès de l'API (`ApiResponse::success`).
 * Voir `backend/app/Support/README.md`.
 */
export interface ApiEnvelope<T> {
  data: T;
  meta?: Record<string, unknown>;
}

/**
 * Forme d'une réponse d'erreur de validation (HTTP 422) de Laravel.
 */
export interface ValidationErrorBody {
  message: string;
  errors: Record<string, string[]>;
}
