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
  // ⚠️ Ce n'est PAS un secret : un identifiant client OAuth est public par
  // construction — il part dans la page, visible de tous. Le secret client, lui,
  // reste côté serveur (et le backend n'en a même pas besoin : il ne fait que
  // vérifier l'audience des jetons d'identité).
  // ⚠️ Le backend a le sien dans son `.env`, mais le frontend est une
  // application séparée qui tourne dans le navigateur : elle ne lit aucun `.env`
  // de Laravel. Il faut donc le renseigner ICI aussi, sinon le bouton reste
  // masqué alors que tout le reste est configuré.
  googleClientId: '561673900142-utmqbrbkvl4d05v3hoo6l5hbsbg4f2m4.apps.googleusercontent.com',
  /**
   * Adresse publique du site en développement (F9.1) — voir le commentaire
   * détaillé dans `environment.ts`. En local, c'est le serveur Angular.
   *
   * ⚠️ Les URL absolues produites ici (`canonical`, `og:url`) pointeront donc
   * vers `localhost` : c'est **voulu**. Vérifier les balises en local a du
   * sens ; y voir le domaine de production n'en aurait aucun, et masquerait
   * une erreur de configuration au déploiement.
   */
  siteUrl: 'http://localhost:4200',
};
