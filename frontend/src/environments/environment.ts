/**
 * Environnement de PRODUCTION (valeur par défaut du build).
 *
 * `apiUrl` doit pointer vers l'API Laravel de production. À ajuster au
 * déploiement (URL relative derrière le même domaine, ou URL complète de l'API).
 */
export const environment = {
  production: true,
  apiUrl: '/api/v1',
  // Identifiant client Google (OAuth) pour le bouton « Connexion Google ».
  // À renseigner par le client via la Google Cloud Console. Tant qu'il est vide,
  // le bouton Google reste masqué (le reste de la connexion fonctionne).
  googleClientId: '',
};
