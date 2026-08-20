import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';
import { RouterLink } from '@angular/router';

import { environment } from '../../../../environments/environment';
import { CookieConsentService } from '../../../core/analytics/cookie-consent.service';

/**
 * Bandeau **de consentement à la mesure d'audience** (F16, 2026-08-20).
 *
 * Monté une seule fois dans la racine (`app.html`), à côté de
 * `app-pwa-banner` : toutes les pages en héritent quel que soit leur layout.
 *
 * ⚠️ **N'apparaît que si la mesure d'audience est effectivement configurée**
 * (`environment.gaMeasurementId` non vide) — inutile de demander un
 * consentement pour un outil qui ne se chargera jamais (développement, démo).
 *
 * ⚠️ **Rien n'est rendu au premier affichage ni au serveur** (le même piège
 * que `PwaBannerComponent`, F8.7) : `estDecide` part de la lecture de
 * `localStorage`, absente au SSR, donc `false` des deux côtés au premier
 * rendu — le bandeau n'apparaît qu'après hydratation, jamais dans un DOM qui
 * diffère entre serveur et client.
 */
@Component({
  selector: 'app-cookie-consent-banner',
  imports: [RouterLink],
  templateUrl: './cookie-consent-banner.html',
  styleUrl: './cookie-consent-banner.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CookieConsentBannerComponent {
  private readonly consentement = inject(CookieConsentService);

  /** Configurée pour CET environnement (vide en développement/démo). */
  private readonly configuree = environment.gaMeasurementId.trim().length > 0;

  /** Visible tant qu'aucun choix n'a été mémorisé, et seulement si configurée. */
  protected readonly visible = computed(() => this.configuree && !this.consentement.estDecide());

  protected accepter(): void {
    this.consentement.accepter();
  }

  protected refuser(): void {
    this.consentement.refuser();
  }
}
