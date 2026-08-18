import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { catchError, map, of } from 'rxjs';

import { PlatformGateService } from '../api/platform-gate.service';

/**
 * `/liste-attente` n'a de sens QUE tant que l'admin a activé la fermeture
 * d'accès (`platform.gate_enabled`, back-office → Paramètres) — c'est la même
 * page que `platformGateGuard` affiche à la place du reste du site quand la
 * plateforme est fermée. Le miroir de cette règle : si l'admin n'a PAS activé
 * l'interrupteur, `/liste-attente` elle-même redirige vers l'accueil — la
 * plateforme s'affiche normalement, pas de page d'attente accessible « dans le
 * vide » par lien direct.
 *
 * ⚠️ Le `gate_enabled` seul décide, pas `bypass` : même un super_admin qui
 * teste le lien direct pendant que la fermeture est active doit voir la page
 * (c'est justement ce qu'il veut vérifier), `bypass` ne le concerne que pour
 * la redirection FORCÉE du reste du site (`platformGateGuard`).
 *
 * En cas d'échec réseau, on affiche la page plutôt que de rediriger : mieux
 * vaut un excès de prudence côté fermeture (voir `platformGateGuard`) que côté
 * affichage d'une page de collecte de contacts, inoffensive si visible à tort.
 */
export const waitlistPageGuard: CanActivateFn = () => {
  const platformGate = inject(PlatformGateService);
  const router = inject(Router);

  return platformGate.check().pipe(
    map((status) => (status.gateEnabled ? true : router.createUrlTree(['/']))),
    catchError(() => of(true)),
  );
};
