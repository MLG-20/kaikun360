import { HttpContextToken, HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

import { AuthService } from '../auth/auth.service';

/**
 * Marqueur posé sur une requête **accessoire**, dont l'échec ne doit PAS
 * emporter la page entière (F10.1).
 *
 * ⚠️ Le comportement par défaut — router vers la page d'erreur dès qu'un appel
 * répond 0 ou 5xx — est le bon pour un appel dont dépend l'écran affiché : sans
 * ses données, la page n'a rien à montrer. Il devient absurde pour un élément de
 * décor : l'assistant Kaikun est un panneau flottant, facultatif, et son
 * interrupteur d'urgence répond justement **503**. Sans ce marqueur, couper
 * l'assistant (`ASSISTANT_ENABLED=false`) éjecterait de sa page quiconque lui
 * écrit — on aurait éteint bien plus que l'assistant.
 *
 * À réserver aux appels réellement secondaires ; en poser un sur le chargement
 * d'une fiche laisserait l'utilisateur devant un écran vide sans explication.
 */
export const SKIP_ERROR_REDIRECT = new HttpContextToken<boolean>(() => false);

/**
 * Gestion centralisée des erreurs HTTP (cahier des charges F0) :
 *   - 401 : session invalide → on vide la session et on renvoie vers la connexion ;
 *   - 0 / 5xx : erreur réseau ou serveur → page d'erreur générique,
 *     **sauf** si la requête porte `SKIP_ERROR_REDIRECT` ;
 *   - 422 : laissé passer tel quel pour que le formulaire affiche les erreurs de champ.
 *
 * L'erreur est toujours propagée pour que l'appelant puisse réagir si besoin.
 */
export const errorInterceptor: HttpInterceptorFn = (req, next) => {
  const router = inject(Router);
  const auth = inject(AuthService);

  return next(req).pipe(
    catchError((error: HttpErrorResponse) => {
      if (error.status === 401) {
        auth.clearSession();
        void router.navigate(['/auth/connexion'], { queryParams: { redirect: router.url } });
      } else if (
        (error.status === 0 || error.status >= 500) &&
        !req.context.get(SKIP_ERROR_REDIRECT) &&
        // Déjà sur la page d'erreur : y renvoyer une seconde fois effacerait le
        // `?depuis=` de la première, donc le seul bouton « Réessayer » utile.
        !router.url.startsWith('/erreur')
      ) {
        // ⚠️ On emporte l'adresse d'où l'on vient (F10.1.a). Sans elle, la page
        // d'erreur ne peut proposer qu'un retour à l'accueil — c'est-à-dire
        // abandonner ce qu'on était en train de faire, alors que la panne est
        // le plus souvent passagère.
        void router.navigate(['/erreur'], { queryParams: { depuis: router.url } });
      }
      // 422 (validation) et 403 : gérés par le composant/route appelant.

      return throwError(() => error);
    }),
  );
};
