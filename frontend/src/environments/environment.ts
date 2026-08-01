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
  // ⚠️ Ce n'est PAS un secret : un identifiant client OAuth est public par
  // construction — il part dans la page, visible de tous. Le secret client, lui,
  // reste côté serveur (et le backend n'en a même pas besoin : il ne fait que
  // vérifier l'audience des jetons d'identité).
  // ⚠️ Le backend a le sien dans son `.env`, mais le frontend est une
  // application séparée qui tourne dans le navigateur : elle ne lit aucun `.env`
  // de Laravel. Il faut donc le renseigner ICI aussi, sinon le bouton reste
  // masqué alors que tout le reste est configuré.
  googleClientId: '924259118421-e0lbqlhfevp2ip0o23ib8d7hheatnons.apps.googleusercontent.com',
};
