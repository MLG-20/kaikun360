import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';

import { AuthService } from '../auth/auth.service';

/**
 * Protège une route : exige une session active. Sinon, redirige vers la connexion
 * en mémorisant l'URL demandée (`redirect`) pour y revenir après authentification.
 */
export const authGuard: CanActivateFn = (_route, state) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  if (auth.isAuthenticated()) {
    return true;
  }

  return router.createUrlTree(['/auth/connexion'], { queryParams: { redirect: state.url } });
};
