import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';

import { AuthService } from '../auth/auth.service';

/**
 * Protège une route par rôle. Les rôles autorisés sont déclarés sur la route via
 * `data: { roles: ['admin', 'agent_kaikun'] }`.
 *
 *   - non authentifié   → redirection connexion ;
 *   - authentifié sans le bon rôle → redirection accueil (accès refusé) ;
 *   - aucun rôle exigé  → laissé passer (équivaut à `authGuard`).
 */
export const roleGuard: CanActivateFn = (route, state) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  if (!auth.isAuthenticated()) {
    return router.createUrlTree(['/auth/connexion'], { queryParams: { redirect: state.url } });
  }

  const roles = (route.data['roles'] as string[] | undefined) ?? [];

  if (roles.length === 0 || auth.hasAnyRole(roles)) {
    return true;
  }

  return router.createUrlTree(['/']);
};
