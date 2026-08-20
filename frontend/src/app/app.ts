import { Component, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';

import { RouteTransitionService } from './core/scroll/route-transition.service';
import { CookieConsentBannerComponent } from './shared/components/cookie-consent-banner/cookie-consent-banner';
import { PwaBannerComponent } from './shared/components/pwa-banner/pwa-banner';

/**
 * Racine applicative (F1.1). Réduite à un point de routage : chaque page est
 * rendue à l'intérieur d'un layout (principal avec en-tête/pied, ou auth avec sa
 * propre signature). Les layouts sont des composants de route, jamais ici.
 *
 * Exception montée ici, globale et en position fixe : le **bandeau PWA**
 * (`app-pwa-banner`, F9.0 — proposition d'installation ou signalement d'une
 * nouvelle version), qui n'appartient à aucun layout et suit l'utilisateur
 * partout. Le lien « revenir en haut » (`app-scroll-top`), lui, est monté
 * par `app-footer` — il ne suit donc plus que les pages qui ont un pied de
 * page (le site public), voir `footer.ts`. La bulle assistant flottante
 * (`app-assistant-launcher`) est montée par chaque layout à côté de son
 * `app-assistant-panel`, voir ce composant.
 *
 * Même logique pour le **bandeau de consentement cookies**
 * (`app-cookie-consent-banner`, F16) : la mesure d'audience concerne le site
 * entier, pas un layout particulier.
 */
@Component({
  selector: 'app-root',
  imports: [RouterOutlet, PwaBannerComponent, CookieConsentBannerComponent],
  templateUrl: './app.html',
  styleUrl: './app.scss',
})
export class App {
  protected readonly voile = inject(RouteTransitionService).voile;
}
