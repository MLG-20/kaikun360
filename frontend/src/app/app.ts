import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';

import { ScrollTopComponent } from './shared/components/scroll-top/scroll-top';

/**
 * Racine applicative (F1.1). Réduite à un point de routage : chaque page est
 * rendue à l'intérieur d'un layout (principal avec en-tête/pied, ou auth avec sa
 * propre signature). Les layouts sont des composants de route, jamais ici.
 *
 * Seule exception montée ici : le bouton **« retour en haut »** (`app-scroll-top`),
 * global à toutes les pages quel que soit leur layout (il est en position fixe et
 * n'apparaît qu'une fois la page défilée).
 */
@Component({
  selector: 'app-root',
  imports: [RouterOutlet, ScrollTopComponent],
  templateUrl: './app.html',
  styleUrl: './app.scss',
})
export class App {}
