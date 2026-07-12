import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';

import { environment } from '../../../environments/environment';
import { AuthService } from '../auth/auth.service';

/**
 * Ajoute l'en-tête `Authorization: Bearer <token>` aux requêtes vers NOTRE API
 * lorsqu'une session est active. On ne l'ajoute jamais aux appels externes, pour
 * ne pas fuiter le jeton.
 */
export const tokenInterceptor: HttpInterceptorFn = (req, next) => {
  const token = inject(AuthService).token;

  if (token && req.url.startsWith(environment.apiUrl)) {
    req = req.clone({ setHeaders: { Authorization: `Bearer ${token}` } });
  }

  return next(req);
};
