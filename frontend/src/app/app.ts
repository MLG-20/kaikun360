import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';

/**
 * Racine applicative (F1.1). Réduite à un point de routage : chaque page est
 * rendue à l'intérieur d'un layout (principal avec en-tête/pied, ou auth avec sa
 * propre signature). Les layouts sont des composants de route, jamais ici.
 */
@Component({
  selector: 'app-root',
  imports: [RouterOutlet],
  templateUrl: './app.html',
  styleUrl: './app.scss',
})
export class App {}
