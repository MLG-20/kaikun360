import { inject } from '@angular/core';
import { ActivatedRouteSnapshot, CanActivateFn, Router } from '@angular/router';
import { catchError, map, of } from 'rxjs';

import { PlatformGateService } from '../api/platform-gate.service';

/**
 * Fermeture d'accès avant ouverture officielle (2026-08-14).
 *
 * Tant que le réglage back-office est actif, seuls le super_admin et les
 * comptes portant `acces:plateforme` (case « accès anticipé » de la fiche
 * compte) peuvent naviguer ailleurs que sur `/liste-attente` — le reste est
 * renvoyé là-bas, y compris les 4 espaces connectés (client/propriétaire/
 * prestataire/entreprise). Le back-office (`/back-office`) et la connexion
 * (`/auth/*`) ne portent PAS ce garde : ils restent joignables sans condition.
 *
 * Routes exemptées via `data: { gateExempt: true }` (liste d'attente, contact,
 * FAQ, pages légales) : aucun appel réseau, on laisse passer directement.
 *
 * ⚠️ En cas d'échec réseau, on bloque **par défaut** (`catchError` → renvoi
 * vers la liste d'attente) plutôt que de laisser passer : mieux vaut un excès
 * de prudence qu'une plateforme non ouverte exposée sur un doute.
 */
export const platformGateGuard: CanActivateFn = (route: ActivatedRouteSnapshot) => {
  if (route.data['gateExempt']) {
    return true;
  }

  const platformGate = inject(PlatformGateService);
  const router = inject(Router);

  return platformGate.check().pipe(
    map((status) => (status.gateEnabled && !status.bypass ? router.createUrlTree(['/liste-attente']) : true)),
    catchError(() => of(router.createUrlTree(['/liste-attente']))),
  );
};
