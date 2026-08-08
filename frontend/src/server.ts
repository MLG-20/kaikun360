import {
  AngularNodeAppEngine,
  createNodeRequestHandler,
  isMainModule,
  writeResponseToNodeResponse,
} from '@angular/ssr/node';
import express from 'express';
import { join } from 'node:path';

const browserDistFolder = join(import.meta.dirname, '../browser');

const app = express();
const angularApp = new AngularNodeAppEngine();

/**
 * Origine de l'API Laravel, vue **depuis ce processus Node** (F9.2).
 *
 * ⚠️ Ce n'est PAS `environment.apiUrl` : en production celui-ci vaut `/api/v1`,
 * une adresse relative qui n'a de sens que dans un navigateur. Ce serveur, lui,
 * doit savoir à quelle machine s'adresser. D'où une variable d'environnement
 * dédiée, à renseigner au déploiement.
 */
const apiOrigin = (process.env['API_ORIGIN'] ?? 'http://localhost:8000').replace(/\/+$/, '');

/**
 * Plan du site — relais vers Laravel (F9.2).
 *
 * ⚠️ **Un moteur n'accepte un `sitemap.xml` que servi par le domaine qu'il
 * décrit.** Le document est construit par Laravel (qui seul connaît les fiches
 * publiées, cf. `App\Support\Seo\SitemapBuilder`), mais il doit sortir sous
 * l'adresse du SITE. Ce relais l'assure quelle que soit l'infrastructure — y
 * compris quand l'API vit sur un autre domaine, cas où une simple règle de
 * reverse-proxy ne suffirait pas.
 *
 * ⚠️ Déclaré **avant** `express.static` et avant le rendu Angular : sans quoi
 * `/sitemap.xml` tomberait dans le gestionnaire universel et recevrait la page
 * d'accueil de l'application en HTML.
 *
 * En cas d'API injoignable on répond **503 et pas un document vide** : un plan
 * du site vide se lit comme « ce site n'a plus aucune page » et peut faire
 * désindexer des fiches valides. Une erreur, elle, fait simplement réessayer.
 */
app.get('/sitemap.xml', async (_req, res) => {
  try {
    const amont = await fetch(`${apiOrigin}/sitemap.xml`);
    if (!amont.ok) {
      throw new Error(`sitemap amont: HTTP ${amont.status}`);
    }
    res.type('application/xml').send(await amont.text());
  } catch (erreur) {
    console.error('[sitemap] amont injoignable', erreur);
    res.status(503).type('text/plain').send('Plan du site momentanément indisponible.');
  }
});

/**
 * Serve static files from /browser
 */
app.use(
  express.static(browserDistFolder, {
    maxAge: '1y',
    index: false,
    redirect: false,
  }),
);

/**
 * Handle all other requests by rendering the Angular application.
 */
app.use((req, res, next) => {
  angularApp
    .handle(req)
    .then((response) => (response ? writeResponseToNodeResponse(response, res) : next()))
    .catch(next);
});

/**
 * Start the server if this module is the main entry point, or it is ran via PM2.
 * The server listens on the port defined by the `PORT` environment variable, or defaults to 4000.
 */
if (isMainModule(import.meta.url) || process.env['pm_id']) {
  const port = process.env['PORT'] || 4000;
  app.listen(port, (error) => {
    if (error) {
      throw error;
    }

    console.log(`Node Express server listening on http://localhost:${port}`);
  });
}

/**
 * Request handler used by the Angular CLI (for dev-server and during build) or Firebase Cloud Functions.
 */
export const reqHandler = createNodeRequestHandler(app);
