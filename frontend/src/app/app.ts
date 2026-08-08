import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';

import { PwaBannerComponent } from './shared/components/pwa-banner/pwa-banner';
import { ScrollTopComponent } from './shared/components/scroll-top/scroll-top';

/**
 * Racine applicative (F1.1). Réduite à un point de routage : chaque page est
 * rendue à l'intérieur d'un layout (principal avec en-tête/pied, ou auth avec sa
 * propre signature). Les layouts sont des composants de route, jamais ici.
 *
 * Deux exceptions montées ici, toutes deux globales et en position fixe :
 * le bouton **« retour en haut »** (`app-scroll-top`, visible une fois la page
 * défilée) et le **bandeau PWA** (`app-pwa-banner`, F9.0 — proposition
 * d'installation ou signalement d'une nouvelle version). Aucun des deux
 * n'appartient à un layout : ils suivent l'utilisateur partout.
 */
@Component({
  selector: 'app-root',
  imports: [RouterOutlet, ScrollTopComponent, PwaBannerComponent],
  templateUrl: './app.html',
  styleUrl: './app.scss',
})
export class App {}
