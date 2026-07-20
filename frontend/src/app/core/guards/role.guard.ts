import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';

import { AuthService } from '../auth/auth.service';
import { spaceHomeFor } from '../auth/space-home';

/**
 * Protège une route par rôle. Les rôles autorisés sont déclarés sur la route via
 * `data: { roles: ['admin', 'agent_kaikun'] }`.
 *
 *   - non authentifié   → redirection connexion ;
 *   - authentifié sans le bon rôle → renvoyé vers **SON PROPRE espace**
 *     (`spaceHomeFor`) plutôt que vers l'accueil : les espaces étant cloisonnés
 *     par rôle, un utilisateur qui tente celui d'un autre profil est ramené chez
 *     lui, ce qui est plus utile qu'un renvoi à la page d'accueil ;
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

  // Accès refusé : on ramène l'utilisateur dans l'espace qui est le sien.
  const home = spaceHomeFor(auth.user());

  // Garde-fou anti-boucle : si l'URL refusée appartient déjà à l'espace qu'on
  // s'apprête à lui proposer (ex. un compte sans rôle `client` bloqué sur
  // `/mon-espace/profil`), le renvoyer là-bas le ferait juste rebloquer → on
  // retombe sur l'accueil public.
  return router.createUrlTree([state.url.startsWith(home) ? '/' : home]);
};
