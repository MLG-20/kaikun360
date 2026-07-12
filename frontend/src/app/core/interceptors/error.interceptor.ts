import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

import { AuthService } from '../auth/auth.service';

/**
 * Gestion centralisée des erreurs HTTP (cahier des charges F0) :
 *   - 401 : session invalide → on vide la session et on renvoie vers la connexion ;
 *   - 0 / 5xx : erreur réseau ou serveur → page d'erreur générique ;
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
      } else if (error.status === 0 || error.status >= 500) {
        void router.navigate(['/erreur']);
      }
      // 422 (validation) et 403 : gérés par le composant/route appelant.

      return throwError(() => error);
    }),
  );
};
