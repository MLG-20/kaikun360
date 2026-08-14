import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';

import { environment } from '../../../environments/environment';

/** Réponse de `GET /platform-status` — miroir de `PlatformStatusController`. */
export interface PlatformStatus {
  gateEnabled: boolean;
  bypass: boolean;
}

/**
 * Fermeture d'accès avant ouverture officielle (2026-08-14).
 *
 * ⚠️ **Pas de mise en cache façon `HeroService`** : contrairement à un
 * bandeau décoratif, la réponse dépend de LA SESSION COURANTE (`bypass`
 * change dès qu'on se connecte/déconnecte) — `platformGateGuard` doit donc
 * relire l'état à CHAQUE navigation, jamais une resucée d'un appel antérieur.
 */
@Injectable({ providedIn: 'root' })
export class PlatformGateService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** GET /platform-status — état courant (public, tient compte du jeton s'il y en a un). */
  check(): Observable<PlatformStatus> {
    return this.http
      .get<{ data: { gate_enabled: boolean; bypass: boolean } }>(`${this.api}/platform-status`)
      .pipe(
        map((res) => ({
          gateEnabled: res.data.gate_enabled,
          bypass: res.data.bypass,
        })),
      );
  }
}
