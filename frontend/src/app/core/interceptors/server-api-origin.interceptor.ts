import { HttpInterceptorFn } from '@angular/common/http';
import { InjectionToken, inject } from '@angular/core';

/**
 * Origine absolue de l'API, fournie **uniquement au rendu serveur** (F9.1).
 *
 * Sa valeur vient de la variable d'environnement `API_ORIGIN` du processus Node
 * (voir `app.config.server.ts`). Dans le navigateur, ce jeton vaut `null` et
 * l'intercepteur ci-dessous ne fait rien.
 */
export const API_ORIGIN = new InjectionToken<string | null>('API_ORIGIN', {
  providedIn: 'root',
  factory: () => null,
});

/**
 * Rend absolues, **au rendu serveur seulement**, les URL d'API relatives.
 *
 * ## Le défaut que cet intercepteur corrige
 *
 * ⚠️ **En production, `environment.apiUrl` vaut `/api/v1`** — une adresse
 * relative, qui n'a de sens que dans un navigateur : elle s'y résout contre la
 * page courante. Le serveur de rendu, lui, est un processus Node ; il n'a pas de
 * « page courante ». Une requête vers `/api/v1/properties/98` s'y résolvait donc
 * contre **sa propre origine** (le port 4000), qui répond du HTML, pas du JSON.
 *
 * Le symptôme était **entièrement silencieux et pourtant grave** : au rendu
 * serveur, chaque fiche du catalogue répondait « introuvable ». Le visiteur ne
 * voyait rien (le navigateur refaisait l'appel, correctement, à l'hydratation),
 * mais **un robot n'exécute pas de JavaScript** : Google et l'aperçu WhatsApp ne
 * voyaient jamais que la page d'erreur — ni le titre du bien, ni sa photo, ni ses
 * données structurées. Autrement dit, tout le travail de référencement de F9.1
 * n'existait qu'en développement, où `apiUrl` est absolue par hasard.
 *
 * Le défaut date du rendu serveur lui-même (F2.9) ; il ne devient visible qu'en
 * regardant le HTML **envoyé**, jamais l'écran.
 *
 * ## Pourquoi un intercepteur plutôt qu'un réglage
 *
 * 26 fichiers lisent `environment.apiUrl` directement. Les faire tous passer par
 * un jeton injectable serait une refonte pour un problème qui ne concerne qu'un
 * seul environnement d'exécution. Ici, une seule règle, appliquée au seul endroit
 * où toutes les requêtes passent — et **strictement inerte dans le navigateur**,
 * où `API_ORIGIN` n'est pas fourni.
 */
export const serverApiOriginInterceptor: HttpInterceptorFn = (req, next) => {
  const origine = inject(API_ORIGIN);

  // Navigateur (jeton absent), ou URL déjà absolue : rien à faire.
  if (!origine || !req.url.startsWith('/')) {
    return next(req);
  }

  return next(req.clone({ url: `${origine}${req.url}` }));
};
