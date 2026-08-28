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
  googleClientId: '561673900142-utmqbrbkvl4d05v3hoo6l5hbsbg4f2m4.apps.googleusercontent.com',
  /**
   * Identifiant de mesure Google Analytics 4 (F16, 2026-08-20).
   *
   * ⚠️ Comme `googleClientId`, ce n'est PAS un secret : un ID de mesure GA4
   * part dans la page, visible de tous — la protection tient à la
   * propriété Google Analytics elle-même, pas à cacher cet identifiant.
   *
   * ⚠️ Vide dans `environment.development.ts` et `environment.demo.ts` :
   * la mesure d'audience n'est active QU'EN PRODUCTION (décision du
   * 2026-08-20) — le développement local et les démonstrations ngrok ne
   * doivent pas polluer les statistiques réelles. `AnalyticsService`
   * (`core/analytics/`) ne charge `gtag.js` que si cette valeur est non
   * vide ET que le visiteur a consenti via le bandeau cookies.
   */
  gaMeasurementId: 'G-Q9545ZH72P',
  /**
   * DSN du projet Sentry "Angular" (monitoring des erreurs, 2026-08-28).
   *
   * ⚠️ Comme `gaMeasurementId`, ce n'est PAS un secret : un DSN Sentry ne fait
   * qu'indiquer OÙ envoyer les événements, il ne donne aucun accès en lecture
   * au projet. Vide dans `environment.development.ts` et `environment.demo.ts`
   * (mêmes raisons que GA4 : ne pas polluer le projet de production).
   *
   * À REMPLIR après création du projet "Angular" sur sentry.io — voir
   * `core/monitoring/sentry.init.ts`, qui ne s'active que si cette valeur
   * est non vide.
   */
  sentryDsn: 'https://3c8473016eaf7d480c3c83214e64e7e6@o4511990876733440.ingest.de.sentry.io/4511990954721360',
  /**
   * Adresse PUBLIQUE du site, sans barre oblique finale (F9.1).
   *
   * ⚠️ Ce n'est pas un doublon de `apiUrl` : `apiUrl` désigne l'API Laravel,
   * `siteUrl` désigne le site que voient les visiteurs et les robots. Le
   * pendant côté backend est `FRONTEND_URL` (utilisé par les e-mails depuis
   * F8.8) — **les deux doivent porter la même valeur**.
   *
   * ⚠️ Une URL **absolue est obligatoire** : les balises `canonical`,
   * `og:url` et `og:image` sont lues par des robots qui n'ont aucun contexte
   * de page. Un chemin relatif y est ignoré (au mieux) ou résolu contre le
   * domaine du réseau social (au pire). C'est aussi pourquoi cette valeur ne
   * peut pas être déduite de `window.location` : au rendu serveur (SSR), il
   * n'y a pas de `window` — et c'est précisément le rendu que le robot lit.
   *
   * À ajuster au déploiement : c'est le SEUL endroit à changer.
   */
  siteUrl: 'https://kaikun360.com',
};
