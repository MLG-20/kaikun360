/**
 * Environnement de DÉVELOPPEMENT (remplace environment.ts via angular.json
 * lors d'un build/serve en configuration `development`).
 *
 * Par défaut, l'API Laravel locale écoute sur http://localhost:8000
 * (`php artisan serve`), préfixe `/api/v1`.
 */
export const environment = {
  production: false,
  apiUrl: 'http://localhost:8000/api/v1',
  // Identifiant client Google (OAuth) pour le bouton « Connexion Google ».
  // Coller ici l'ID de la Google Cloud Console pour tester en local. Vide = le
  // bouton Google reste masqué.
  googleClientId: '',
};
