import { TestBed } from '@angular/core/testing';
import { ActivatedRouteSnapshot, Router, UrlTree } from '@angular/router';
import { firstValueFrom, isObservable, of, throwError } from 'rxjs';

import { PlatformGateService } from '../api/platform-gate.service';
import { platformGateGuard } from './platform-gate.guard';

/**
 * Fermeture d'accès avant ouverture (2026-08-14). Ce que ce test protège :
 * une route exemptée ne doit JAMAIS déclencher d'appel réseau, et un échec
 * réseau doit bloquer (par excès de prudence), pas laisser passer.
 */
describe('platformGateGuard', () => {
  const route = (data: Record<string, unknown> = {}) =>
    ({ data }) as unknown as ActivatedRouteSnapshot;

  const setUp = (check: () => ReturnType<PlatformGateService['check']>) => {
    TestBed.configureTestingModule({
      providers: [{ provide: PlatformGateService, useValue: { check } }],
    });
    return TestBed.inject(Router);
  };

  /** Résout la sortie du garde, qu'elle soit synchrone (exempté) ou async. */
  const run = async (data?: Record<string, unknown>) => {
    const outcome = TestBed.runInInjectionContext(() => platformGateGuard(route(data), {} as never));
    return isObservable(outcome) ? firstValueFrom(outcome) : outcome;
  };

  it('laisse passer une route exemptée SANS appeler le serveur', async () => {
    const check = vi.fn(() => of({ gateEnabled: true, bypass: false }));
    setUp(check);

    expect(await run({ gateExempt: true })).toBe(true);
    expect(check).not.toHaveBeenCalled();
  });

  it('laisse passer quand la plateforme est ouverte', async () => {
    setUp(() => of({ gateEnabled: false, bypass: false }));

    expect(await run()).toBe(true);
  });

  it('laisse passer un compte avec accès anticipé', async () => {
    setUp(() => of({ gateEnabled: true, bypass: true }));

    expect(await run()).toBe(true);
  });

  it('renvoie vers la liste d’attente quand la plateforme est fermée', async () => {
    const router = setUp(() => of({ gateEnabled: true, bypass: false }));

    const result = (await run()) as UrlTree;

    expect(router.serializeUrl(result)).toBe('/liste-attente');
  });

  it('bloque par défaut si le serveur ne répond pas', async () => {
    const router = setUp(() => throwError(() => new Error('panne')));

    const result = (await run()) as UrlTree;

    expect(router.serializeUrl(result)).toBe('/liste-attente');
  });
});
